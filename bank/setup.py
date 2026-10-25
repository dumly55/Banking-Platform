"""Creates the database and tables, and optionally loads demo data.

    flask --app bank setup-db            create the database + tables (refuses if tables already exist)
    flask --app bank setup-db --seed     ...and add demo customers, employees and transactions
    flask --app bank setup-db --fresh    drop and recreate everything (destroys existing data!)
    flask --app bank setup-db --fresh --simulate
                                         ...plus ~2,000 simulated customers with two years of history (see simulate.py)
"""
import re
import secrets
from decimal import Decimal

import click

from . import banking, config, db, simulate
from .auth import hash_password
from .validation import ssn_fingerprint


def ensure_app_key():
    """Generates the secret used to fingerprint SSNs and sign sessions, and saves it in .env."""
    if config.KEY:
        return
    config.KEY = secrets.token_hex(32)
    env_file = config.ROOT / '.env'
    text = env_file.read_text() if env_file.exists() else ''
    if re.search(r'^APP_KEY=.*$', text, flags=re.M):
        text = re.sub(r'^APP_KEY=.*$', f'APP_KEY={config.KEY}', text, count=1, flags=re.M)
    else:
        text += f'\nAPP_KEY={config.KEY}\n'
    env_file.write_text(text)
    click.echo('Generated APP_KEY in .env (keep it secret and keep it stable).')


@click.command('setup-db')
@click.option('--seed', is_flag=True, help='Also add demo customers, employees and transactions.')
@click.option('--fresh', is_flag=True, help='Drop and recreate everything. Destroys existing data!')
@click.option('--simulate', 'with_history', is_flag=True,
              help='Also add ~2,000 simulated customers with two years of history, for analysis.')
def setup_db(seed, fresh, with_history):
    """Create the database and tables, and optionally load demo data."""
    seed = seed or fresh or with_history
    ensure_app_key()

    # Demo accounts have published passwords, so they must never exist on a live server.
    if seed and config.ENV == 'production':
        raise click.ClickException('Refusing to load demo data while APP_ENV=production (demo passwords are public).\n'
                                   'Run without --seed / --fresh, or set APP_ENV=development for a local copy.')

    name = config.DB['name']
    server = db.connect(database=False)
    with server.cursor() as cursor:
        cursor.execute(f'CREATE DATABASE IF NOT EXISTS `{name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')
    server.close()

    if db.value('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s', (name,)) and not fresh:
        raise click.ClickException(f"Database '{name}' already has tables. Re-run with --fresh to wipe and recreate it.")

    schema = (config.ROOT / 'database' / 'schema.sql').read_text()
    for statement in re.split(r';\s*\n', schema):
        statement = re.sub(r'^\s*--.*$', '', statement, flags=re.M).strip()
        if statement:
            db.execute(statement)
    click.echo(f"Created tables in '{name}'.")

    if seed:
        load_demo_data()
    if with_history:
        click.echo('Simulating two years of activity...')
        counts = simulate.load()
        click.echo('Added ' + ', '.join(f'{count:,} {table}' for table, count in counts.items()) + '.')


def load_demo_data():
    """Demo data. Every password is Password123."""
    password = hash_password('Password123')

    employees = [
        # id, email, tier, manager, first, last, phone, ssn, license
        (100000001, 'admin@bankofthefuture.test', 3, None, 'Alex', 'Morgan', '4085550101', '900000001', 'A1000001'),
        (100000002, 'manager@bankofthefuture.test', 2, 100000001, 'Jordan', 'Lee', '4085550102', '900000002', 'A1000002'),
        (100000003, 'teller@bankofthefuture.test', 1, 100000002, 'Sam', 'Rivera', '4085550103', '900000003', 'A1000003'),
    ]
    for id_, email, tier, manager, first, last, phone, ssn, license_ in employees:
        db.execute(
            "INSERT INTO employees (id, email, password_hash, status, tier, manager_id, first_name, last_name, phone, ssn_hash,"
            " license_state, license_number, address1, city, state, zip)"
            " VALUES (%s, %s, %s, 'Hired', %s, %s, %s, %s, %s, %s, 'CA', %s, '1 Washington Sq', 'San Jose', 'CA', '95192')",
            (id_, email, password, tier, manager, first, last, phone, ssn_fingerprint(ssn), license_),
        )
    # A pending offer, so the Terminate list and new-hire registration have something to show.
    db.execute("INSERT INTO employees (id, email, status, tier, manager_id) VALUES (100000004, 'newhire@bankofthefuture.test', 'Pending', 1, 100000002)")

    customers = [
        # username, pin, first, last, email, phone, ssn, license
        ('demo', '1234', 'Taylor', 'Chen', 'demo@bankofthefuture.test', '4085550111', '800000001', 'D1000001'),
        ('jdoe', '4321', 'Jamie', 'Doe', 'jamie@bankofthefuture.test', '4085550112', '800000002', 'D1000002'),
    ]
    user_ids = {}
    for username, pin, first, last, email, phone, ssn, license_ in customers:
        db.execute(
            "INSERT INTO users (username, password_hash, pin_hash, first_name, last_name, email, phone, ssn_hash,"
            " license_state, license_number, address1, city, state, zip)"
            " VALUES (%s, %s, %s, %s, %s, %s, %s, %s, 'CA', %s, '100 Market St', 'San Jose', 'CA', '95113')",
            (username, password, hash_password(pin), first, last, email, phone, ssn_fingerprint(ssn), license_),
        )
        user_ids[username] = db.value('SELECT LAST_INSERT_ID()')

    accounts = [
        # number, owner, type, opened (days ago)
        ('4100123456780001', 'demo', 'Checking', 90),
        ('4200123456780002', 'demo', 'Savings', 90),
        ('4100987654320003', 'jdoe', 'Checking', 60),
    ]
    for number, owner, kind, days_ago in accounts:
        db.execute(
            'INSERT INTO accounts (account_number, user_id, type, interest_rate, interest_applied_on, created_at)'
            ' VALUES (%s, %s, %s, %s, CURDATE() - INTERVAL %s DAY, NOW() - INTERVAL %s DAY)',
            (number, user_ids[owner], kind, banking.ACCOUNT_TYPES[kind][1], days_ago, days_ago),
        )
    # Each demo customer signed up when they opened their first account, not on the day setup ran.
    db.execute('UPDATE users u JOIN (SELECT user_id, MIN(created_at) AS opened FROM accounts GROUP BY user_id) a'
               ' ON a.user_id = u.id SET u.created_at = a.opened')

    history = [
        # account, type, amount, description, days ago
        ('4100123456780001', 'Deposit', '2500.00', 'Mobile check deposit', 88),
        ('4200123456780002', 'Deposit', '8000.00', 'Mobile check deposit', 87),
        ('4100987654320003', 'Deposit', '1200.00', 'Mobile check deposit', 59),
        ('4100123456780001', 'Withdrawal', '-120.00', 'ATM withdrawal', 40),
        ('4100123456780001', 'Withdrawal', '-60.00', 'ATM withdrawal', 12),
    ]
    for number, kind, amount, description, days_ago in history:
        before = db.value('SELECT balance FROM accounts WHERE account_number = %s', (number,))
        transaction_id = banking.record_transaction(number, kind, 'Processed', Decimal(amount), before, description)
        db.execute('UPDATE transactions SET created_at = NOW() - INTERVAL %s DAY WHERE id = %s', (days_ago, transaction_id))
        db.execute('UPDATE accounts SET balance = balance + %s WHERE account_number = %s', (Decimal(amount), number))
    banking.transfer(user_ids['demo'], '4200123456780002', '4100123456780001', Decimal('500.00'))
    banking.transfer(user_ids['demo'], '4100123456780001', '4100987654320003', Decimal('75.50'))

    tickets = [
        ('Morgan Bailey', 'morgan@example.com', 'How long does a mobile check deposit usually take to clear?', 'Open'),
        ('Chris Patel', 'chris@example.com', 'Can I open a joint savings account with my spouse?', 'Open'),
        ('Riley Kim', 'riley@example.com', 'I forgot my username, how do I recover it?', 'Resolved'),
    ]
    for name, email, question, status in tickets:
        db.execute('INSERT INTO support_tickets (id, name, email, question, status) VALUES (%s, %s, %s, %s, %s)',
                   (db.unique_number('support_tickets', 'id', 10), name, email, question, status))

    click.echo(
        'Seeded demo data. Every password is Password123.\n'
        '  Customers:  demo (PIN 1234), jdoe (PIN 4321)\n'
        '  Employees:  100000001 (admin, tier 3), 100000002 (manager, tier 2), 100000003 (teller, tier 1)\n'
        '  New hire:   employee ID 100000004 / newhire@bankofthefuture.test can register at /employee-register'
    )
