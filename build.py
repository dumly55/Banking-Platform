"""Simulates two years of Bank of the Future activity and loads it into SQLite.

    python build.py        writes data/bank.db

The simulation keeps the original web app's rules (src/banking.php in the git history): check
deposits post only once an employee approves them, withdrawals and transfers that would overdraw
an account are refused, and interest compounds once a year on each account's opening anniversary.
"""
import heapq
import itertools
import math
import random
import sqlite3
from datetime import datetime, timedelta
from pathlib import Path

ROOT = Path(__file__).parent
START = datetime(2024, 10, 1)
END = datetime(2026, 10, 1)   # exclusive: two full years
YEAR = timedelta(days=365)
CUSTOMERS = 2000

ACCOUNT_TYPES = {
    # type: (account number prefix, annual interest rate), as in the web app
    'Checking': ('4100', 0.0005),
    'Savings': ('4200', 0.0057),
}

EMPLOYEES = [
    # id, tier (1 teller, 2 manager, 3 admin), manager id, hired
    (1, 3, None, START),
    (2, 2, 1, START),
    (3, 1, 2, START),
    (4, 1, 2, START),
    (5, 2, 1, datetime(2025, 6, 2)),
    (6, 1, 5, datetime(2025, 6, 16)),
    (7, 1, 5, datetime(2026, 1, 12)),
]

# Population in millions (2020 census), so customers spread across states like the US population.
STATES = {
    'CA': 39.5, 'TX': 29.1, 'FL': 21.5, 'NY': 20.2, 'PA': 13.0, 'IL': 12.8, 'OH': 11.8, 'GA': 10.7,
    'NC': 10.4, 'MI': 10.1, 'NJ': 9.3, 'VA': 8.6, 'WA': 7.7, 'AZ': 7.2, 'MA': 7.0, 'TN': 6.9,
    'IN': 6.8, 'MD': 6.2, 'MO': 6.2, 'WI': 5.9, 'CO': 5.8, 'MN': 5.7, 'SC': 5.1, 'AL': 5.0,
    'LA': 4.7, 'KY': 4.5, 'OR': 4.2, 'OK': 4.0, 'CT': 3.6, 'UT': 3.3, 'IA': 3.2, 'NV': 3.1,
    'AR': 3.0, 'MS': 3.0, 'KS': 2.9, 'NM': 2.1, 'NE': 2.0, 'ID': 1.8, 'WV': 1.8, 'HI': 1.5,
    'NH': 1.4, 'ME': 1.4, 'RI': 1.1, 'MT': 1.1, 'DE': 1.0, 'SD': 0.9, 'ND': 0.8, 'AK': 0.7,
    'DC': 0.7, 'VT': 0.6, 'WY': 0.6,
}


def stamp(t):
    return t.strftime('%Y-%m-%d %H:%M:%S')


def business_hours(t):
    """Moves t forward to the next time the review team is working: weekdays, 9:00 to 17:00."""
    opening = lambda day: day.replace(hour=9, minute=0, second=0, microsecond=0)
    if t.hour >= 17:
        t = opening(t + timedelta(days=1))
    elif t.hour < 9:
        t = opening(t)
    while t.weekday() >= 5:
        t = opening(t + timedelta(days=1))
    return t


def every(t, days, until):
    while t < until:
        yield t
        t += timedelta(days=days)


def simulate(customers=CUSTOMERS, seed=42):
    """Plays out every customer's activity in time order. Returns {table name: rows}."""
    rng = random.Random(seed)
    rows = {table: [] for table in ('customers', 'employees', 'accounts', 'transactions', 'check_deposits')}
    rows['employees'] = [[id_, tier, manager, stamp(hired)] for id_, tier, manager, hired in EMPLOYEES]
    # (employee id, hired, share of reviews, strictness): tellers do most reviews, the admin none
    reviewers = [(id_, hired, 4 if tier == 1 else 1, rng.uniform(0.5, 2)) for id_, tier, _, hired in EMPLOYEES if tier < 3]
    balance = {}    # account number -> cents
    checking = []   # every checking account, to pick who receives a transfer
    queue, order = [], itertools.count()

    def at(t, action, *args):
        # Anything due at or after END never happens, so late check deposits stay pending.
        if t < END:
            heapq.heappush(queue, (t, next(order), action, args))

    def now_and_then(t, mean_days, until):
        while (t := t + timedelta(days=rng.expovariate(1 / mean_days))) < until:
            yield t

    def record(t, number, kind, status, cents):
        before = None if status == 'Pending' else balance[number] / 100
        rows['transactions'].append(row := [len(rows['transactions']) + 1, number, kind, status, cents / 100, before, None, stamp(t)])
        return row

    def open_account(t, customer_id, kind):
        prefix, rate = ACCOUNT_TYPES[kind]
        number = f'{prefix}{len(rows["accounts"]) + 1:012d}'
        rows['accounts'].append([number, customer_id, kind, 0, rate, stamp(t)])
        balance[number] = 0
        at(t + YEAR, pay_interest, number, rate)
        return number

    def pay_interest(t, number, rate):
        if cents := round(balance[number] * rate):
            record(t, number, 'Interest', 'Processed', cents)
            balance[number] += cents
        at(t + YEAR, pay_interest, number, rate)

    def deposit(t, number, cents):
        row = record(t, number, 'Deposit', 'Pending', cents)
        rows['check_deposits'].append(review := [row[0], None, None])
        reviewed = business_hours(t + timedelta(hours=rng.expovariate(1 / 4))) + timedelta(minutes=rng.uniform(5, 90))
        at(reviewed, review_deposit, row, review, cents)

    def review_deposit(t, row, review, cents):
        on_staff = [r for r in reviewers if r[1] <= t]
        employee_id, _, _, strictness = rng.choices(on_staff, [r[2] for r in on_staff])[0]
        # Most checks clear; checks over $2,500 are denied more often, and some reviewers are stricter.
        approved = rng.random() > 0.02 * strictness * (4 if cents > 250_000 else 1)
        row[3] = 'Processed' if approved else 'Denied'
        row[5] = balance[row[1]] / 100
        if approved:
            balance[row[1]] += cents
        review[1:] = [employee_id, stamp(t)]

    def withdraw(t, number, cents):
        if balance[number] >= cents:   # the ATM refuses to overdraw, and the app keeps no record of refusals
            record(t, number, 'Withdrawal', 'Processed', -cents)
            balance[number] -= cents

    def transfer(t, source, target, cents):
        target = target or rng.choice(checking)   # None: send to a random customer
        if target == source or balance[source] < cents:
            return
        sent, received = record(t, source, 'Transfer Out', 'Processed', -cents), record(t, target, 'Transfer In', 'Processed', cents)
        sent[6], received[6] = received[0], sent[0]
        balance[source] -= cents
        balance[target] += cents

    def open_savings(t, customer_id, checking_number, monthly_cents, leaves):
        savings = open_account(t, customer_id, 'Savings')
        for when in every(t, 30.4, leaves):
            at(when, transfer, checking_number, savings, monthly_cents)

    def sign_up(t, customer_id):
        rows['customers'].append([customer_id, rng.choices(list(STATES), list(STATES.values()))[0], stamp(t)])
        number = open_account(t, customer_id, 'Checking')
        checking.append(number)
        leaves = min(END, t + timedelta(days=rng.expovariate(1 / (4 * 365))))   # when they stop using the bank
        paycheck = round(rng.lognormvariate(math.log(150_000), 0.5))         # cents every two weeks, median $1,500
        monthly_in = paycheck * 26 / 12

        for when in every(t + timedelta(days=rng.uniform(0, 14)), 14, leaves):
            at(when, deposit, number, round(paycheck * rng.uniform(0.95, 1.05)))
        # Most income leaves as ATM cash, in multiples of $20 and at most $1,000 a visit.
        gap = rng.lognormvariate(math.log(7), 0.5)
        per_visit = monthly_in * rng.uniform(0.55, 0.85) * gap / 30.4
        for when in now_and_then(t, gap, leaves):
            at(when, withdraw, number, min(100_000, max(2_000, round(per_visit * rng.uniform(0.5, 1.5) / 2_000) * 2_000)))
        for when in now_and_then(t, 45, leaves):
            at(when, transfer, number, None, rng.randint(10, 200) * 100)
        if rng.random() < 0.45:
            at(t + timedelta(days=rng.uniform(0, 180)), open_savings, customer_id, number, round(monthly_in * rng.uniform(0.05, 0.15)), leaves)

    for customer_id, u in enumerate(sorted(rng.random() for _ in range(customers)), 1):
        # Inverse CDF of a signup rate that triples over the two years.
        at(START + (END - START) * ((math.sqrt(1 + 8 * u) - 1) / 2), sign_up, customer_id)
    while queue:
        t, _, action, args = heapq.heappop(queue)
        action(t, *args)

    for account in rows['accounts']:
        account[3] = balance[account[0]] / 100
    return rows


def build(path, **options):
    """Writes a fresh SQLite database at path and returns an open connection to it."""
    path.unlink(missing_ok=True)
    db = sqlite3.connect(path)
    db.executescript((ROOT / 'sql' / 'schema.sql').read_text())
    for table, data in simulate(**options).items():
        db.executemany(f'INSERT INTO {table} VALUES ({", ".join("?" * len(data[0]))})', data)
    db.commit()
    return db


if __name__ == '__main__':
    (ROOT / 'data').mkdir(exist_ok=True)
    db = build(ROOT / 'data' / 'bank.db')
    for table in ('customers', 'accounts', 'transactions', 'check_deposits'):
        print(f'{table:>15}: {db.execute(f"SELECT COUNT(*) FROM {table}").fetchone()[0]:,}')
