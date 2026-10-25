"""MySQL connection (one per request, opened on first use) and query helpers."""
import secrets
from contextlib import contextmanager
from datetime import datetime
from zoneinfo import ZoneInfo

import pymysql
from flask import g

from . import config


def connect(database=True):
    c = config.DB
    conn = pymysql.connect(
        host=c['host'], port=c['port'], user=c['user'], password=c['password'],
        database=c['name'] if database else None, charset='utf8mb4',
        cursorclass=pymysql.cursors.DictCursor, autocommit=True,
    )
    # Keep NOW()/CURDATE() in the app's timezone.
    offset = datetime.now(ZoneInfo(config.TIMEZONE)).strftime('%z')
    with conn.cursor() as cursor:
        cursor.execute('SET time_zone = %s', (f'{offset[:3]}:{offset[3:]}',))
    return conn


def db():
    if 'db' not in g:
        g.db = connect()
    return g.db


def close(_error=None):
    conn = g.pop('db', None)
    if conn is not None:
        conn.close()


def _run(sql, params):
    cursor = db().cursor()
    cursor.execute(sql, params or None)  # None, not (), so a literal % in parameterless SQL is left alone
    return cursor


def one(sql, params=()):
    with _run(sql, params) as cursor:
        return cursor.fetchone()


def many(sql, params=()):
    with _run(sql, params) as cursor:
        return cursor.fetchall()


def value(sql, params=()):
    row = one(sql, params)
    return next(iter(row.values())) if row else None


def execute(sql, params=()):
    with _run(sql, params) as cursor:
        return cursor.rowcount


def execute_many(sql, rows, batch=2000):
    """Runs an INSERT for many rows, sent as multi-row statements in one transaction."""
    with transaction(), db().cursor() as cursor:
        for start in range(0, len(rows), batch):
            cursor.executemany(sql, rows[start:start + batch])


@contextmanager
def transaction():
    """Runs the block inside a transaction, rolling back if it raises."""
    conn = db()
    conn.begin()
    try:
        yield
        conn.commit()
    except BaseException:
        conn.rollback()
        raise


def unique_number(table, column, digits, prefix=''):
    """Picks a random, unused numeric id of the given length for table.column.
    Ids are random rather than sequential so account and ticket numbers can't be guessed."""
    random_digits = digits - len(prefix)
    for _ in range(20):
        number = prefix + str(secrets.randbelow(10 ** random_digits)).zfill(random_digits)
        if prefix == '' and number[0] == '0':
            continue
        if not value(f'SELECT 1 FROM {table} WHERE {column} = %s', (number,)):
            return number
    raise RuntimeError(f'Could not generate a unique {column}')
