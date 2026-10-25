"""Two years of simulated bank activity, so the database has something to analyze.

    flask --app bank setup-db --simulate

About 2,000 customers sign up between October 2024 and September 2026, at a rate that triples
over the two years, spread across states like the US population. They deposit paychecks by mobile
check, take cash from the ATM, move money to savings and send money to each other. It follows the
app's own rules (banking.py): check deposits post only once an employee approves them, and reviewers
work weekdays 9:00 to 17:00; withdrawals and transfers that would overdraw an account are refused;
interest compounds on each account's opening anniversary. Some customers stop using the bank.

Seeded, so every run produces the same data. Simulated customers log in with Password123 and PIN 1234,
like the demo accounts, and review staff with Password123.
"""
import base64
import heapq
import itertools
import math
import random
from datetime import datetime, timedelta
from decimal import Decimal

from . import config, db
from .auth import hash_password
from .banking import ACCOUNT_TYPES
from .validation import ssn_fingerprint

START = datetime(2024, 10, 1)
END = datetime(2026, 10, 1)  # exclusive: two full years
CUSTOMERS = 2000
CHECK_IMAGE = 'simulated-check.png'  # stand-in image shared by every simulated check deposit

# Review staff hired alongside the demo employees: id, tier (1 teller, 2 manager), manager, hired, first, last
STAFF = [
    (100000005, 1, 100000002, START, 'Priya', 'Shah'),
    (100000006, 2, 100000001, datetime(2025, 6, 2), 'Marcus', 'Bell'),
    (100000007, 1, 100000006, datetime(2025, 6, 16), 'Elena', 'Ruiz'),
    (100000008, 1, 100000006, datetime(2026, 1, 12), 'Owen', 'Park'),
]
# Everyone who reviews checks: the demo manager and teller plus the staff above (the admin doesn't).
REVIEWERS = [(100000002, 2, START), (100000003, 1, START)] + [(id_, tier, hired) for id_, tier, _, hired, *_ in STAFF]

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
FIRST_NAMES = ['James', 'Maria', 'Wei', 'Aisha', 'Carlos', 'Emily', 'Noah', 'Sofia', 'Liam', 'Fatima', 'Ethan', 'Grace',
               'Mateo', 'Hannah', 'Arjun', 'Chloe', 'Daniel', 'Mei', 'Lucas', 'Zoe', 'Omar', 'Ava', 'Kenji', 'Isabella',
               'Samuel', 'Nia', 'Diego', 'Leah', 'Ryan', 'Amara', 'Jonah', 'Lily', 'Andre', 'Priya', 'Tyler', 'Elena']
LAST_NAMES = ['Smith', 'Garcia', 'Chen', 'Johnson', 'Nguyen', 'Williams', 'Patel', 'Brown', 'Kim', 'Jones', 'Lopez',
              'Davis', 'Martinez', 'Lee', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Hernandez',
              'Martin', 'White', 'Thompson', 'Clark', 'Lewis', 'Walker', 'Young', 'Allen', 'King', 'Wright', 'Scott']


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


def add_year(t):
    """The same date next year (Feb 29 becomes Feb 28), matching MySQL's INTERVAL 1 YEAR."""
    try:
        return t.replace(year=t.year + 1)
    except ValueError:
        return t.replace(year=t.year + 1, day=28)


def every(t, days, until):
    while t < until:
        yield t
        t += timedelta(days=days)


def simulate(customers=CUSTOMERS, seed=42, taken_ids=()):
    """Plays out every customer's activity in time order. Money is in cents; returns {table: rows}.
    taken_ids: transaction ids already in the database, which must not be reused."""
    rng = random.Random(seed)
    rows = {'users': [], 'accounts': [], 'transactions': [], 'check_deposits': []}
    # (employee id, hired, share of reviews, strictness): tellers do most reviews; some are stricter than others
    reviewers = [(id_, hired, 4 if tier == 1 else 1, rng.uniform(0.5, 2)) for id_, tier, hired in REVIEWERS]
    balance = {}     # account number -> cents
    accounts = {}    # account number -> its row, to record when interest was last paid
    checking = []    # every checking account, to pick who receives a transfer
    used_ids = set(taken_ids)
    queue, order = [], itertools.count()

    def at(t, action, *args):
        # Anything due at or after END never happens, so late check deposits stay pending.
        if t < END:
            heapq.heappush(queue, (t, next(order), action, args))

    def now_and_then(t, mean_days, until):
        while (t := t + timedelta(days=rng.expovariate(1 / mean_days))) < until:
            yield t

    def new_id():
        while (id_ := str(rng.randrange(10 ** 11, 10 ** 12))) in used_ids:
            pass
        used_ids.add(id_)
        return id_

    def record(t, number, kind, status, cents, description):
        before = None if status == 'Pending' else balance[number]
        rows['transactions'].append(row := [new_id(), number, kind, status, cents, before, description, None, t])
        return row

    def open_account(t, user_id, kind):
        prefix, rate = ACCOUNT_TYPES[kind]
        number = f'{prefix}{len(rows["accounts"]) + 1:012d}'
        accounts[number] = [number, user_id, kind, 0, rate, t.date(), t]
        rows['accounts'].append(accounts[number])
        balance[number] = 0
        at(add_year(t), pay_interest, number, rate)
        return number

    def pay_interest(t, number, rate):
        if cents := round(balance[number] * rate):
            record(t, number, 'Interest', 'Processed', cents, 'Interest payment')
            balance[number] += cents
        accounts[number][5] = t.date()  # interest_applied_on
        at(add_year(t), pay_interest, number, rate)

    def deposit(t, number, cents):
        row = record(t, number, 'Deposit', 'Pending', cents, 'Mobile check deposit')
        rows['check_deposits'].append(review := [row[0], None, None])
        reviewed = business_hours(t + timedelta(hours=rng.expovariate(1 / 4))) + timedelta(minutes=rng.uniform(5, 90))
        at(reviewed, review_deposit, row, review, cents)

    def review_deposit(t, row, review, cents):
        on_staff = [r for r in reviewers if r[1] <= t]
        employee_id, _, _, strictness = rng.choices(on_staff, [r[2] for r in on_staff])[0]
        # Most checks clear; checks over $2,500 are denied more often, and some reviewers are stricter.
        approved = rng.random() > 0.02 * strictness * (4 if cents > 250_000 else 1)
        row[3] = 'Processed' if approved else 'Denied'
        row[5] = balance[row[1]]
        if approved:
            balance[row[1]] += cents
        review[1:] = [employee_id, t]

    def withdraw(t, number, cents):
        if balance[number] >= cents:  # the ATM refuses to overdraw, and the app keeps no record of refusals
            record(t, number, 'Withdrawal', 'Processed', -cents, 'ATM withdrawal')
            balance[number] -= cents

    def transfer(t, source, target, cents):
        target = target or rng.choice(checking)  # None: send to a random customer
        if target == source or balance[source] < cents:
            return
        sent = record(t, source, 'Transfer Out', 'Processed', -cents, f'Transfer to account ending {target[-4:]}')
        received = record(t, target, 'Transfer In', 'Processed', cents, f'Transfer from account ending {source[-4:]}')
        sent[7], received[7] = received[0], sent[0]
        balance[source] -= cents
        balance[target] += cents

    def open_savings(t, user_id, checking_number, monthly_cents, leaves):
        savings = open_account(t, user_id, 'Savings')
        for when in every(t, 30.4, leaves):
            at(when, transfer, checking_number, savings, monthly_cents)

    def sign_up(t, n):
        user_id = 1000 + n
        state = rng.choices(list(STATES), list(STATES.values()))[0]
        rows['users'].append([user_id, rng.choice(FIRST_NAMES), rng.choice(LAST_NAMES), state, t])
        number = open_account(t, user_id, 'Checking')
        checking.append(number)
        leaves = min(END, t + timedelta(days=rng.expovariate(1 / (4 * 365))))  # when they stop using the bank
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
            at(t + timedelta(days=rng.uniform(0, 180)), open_savings, user_id, number, round(monthly_in * rng.uniform(0.05, 0.15)), leaves)

    for n, u in enumerate(sorted(rng.random() for _ in range(customers)), 1):
        # Inverse CDF of a signup rate that triples over the two years.
        at(START + (END - START) * ((math.sqrt(1 + 8 * u) - 1) / 2), sign_up, n)
    while queue:
        t, _, action, args = heapq.heappop(queue)
        action(t, *args)

    for account in rows['accounts']:
        account[3] = balance[account[0]]
    return rows


def dollars(cents):
    return None if cents is None else Decimal(cents) / 100


def load(customers=CUSTOMERS):
    """Adds the review staff, simulated customers and their history to a freshly seeded database.
    Returns how many rows went into each table."""
    sim = simulate(customers, taken_ids={row['id'] for row in db.many('SELECT id FROM transactions')})
    password, pin = hash_password('Password123'), hash_password('1234')

    # The demo data is dated relative to today. Move it back so the whole dataset ends where the
    # simulation does, and analysis gives the same answers whenever the database was built.
    days = max(0, math.ceil((datetime.now() - END) / timedelta(days=1)))
    for table, column in [('users', 'created_at'), ('accounts', 'created_at'), ('accounts', 'interest_applied_on'),
                          ('transactions', 'created_at'), ('support_tickets', 'created_at')]:
        db.execute(f'UPDATE {table} SET {column} = {column} - INTERVAL %s DAY', (days,))

    # The demo staff review simulated checks from the first day, so they were hired then.
    db.execute('UPDATE employees SET created_at = %s WHERE id IN (100000001, 100000002, 100000003)', (START,))
    db.execute_many(
        "INSERT INTO employees (id, email, password_hash, status, tier, manager_id, first_name, last_name, phone, ssn_hash,"
        " license_state, license_number, address1, city, state, zip, created_at)"
        " VALUES (%s, %s, %s, 'Hired', %s, %s, %s, %s, %s, %s, 'CA', %s, '1 Washington Sq', 'San Jose', 'CA', '95192', %s)",
        [(id_, f'{first.lower()}.{last.lower()}@bankofthefuture.test', password, tier, manager, first, last,
          f'40855502{id_ % 100:02d}', ssn_fingerprint(f'9100{id_ % 100000:05d}'), f'A2{id_ % 1000000:06d}', hired)
         for id_, tier, manager, hired, first, last in STAFF],
    )
    # Addresses aren't used by the app or the analysis, so every customer shares a placeholder street.
    db.execute_many(
        'INSERT INTO users (id, username, password_hash, pin_hash, first_name, last_name, email, phone, ssn_hash,'
        ' license_state, license_number, address1, city, state, zip, created_at)'
        " VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, '1 Main St', 'Springfield', %s, '00000', %s)",
        [(id_, f'{first}.{last}{id_}'.lower(), password, pin, first, last, f'{first}.{last}{id_}@example.com'.lower(),
          f'5{id_:09d}', ssn_fingerprint(f'6{id_:08d}'), state, f'S{id_:07d}', state, signed_up)
         for id_, first, last, state, signed_up in sim['users']],
    )
    db.execute_many(
        'INSERT INTO accounts (account_number, user_id, type, balance, interest_rate, interest_applied_on, created_at)'
        ' VALUES (%s, %s, %s, %s, %s, %s, %s)',
        [(number, user_id, kind, dollars(cents), rate, applied, opened)
         for number, user_id, kind, cents, rate, applied, opened in sim['accounts']],
    )
    db.execute_many(
        'INSERT INTO transactions (id, account_number, type, status, amount, balance_before, description, related_transaction, created_at)'
        ' VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)',
        [(id_, number, kind, status, dollars(cents), dollars(before), description, related, t)
         for id_, number, kind, status, cents, before, description, related, t in sim['transactions']],
    )
    db.execute_many(
        'INSERT INTO check_deposits (transaction_id, front_image, back_image, reviewed_by, reviewed_at) VALUES (%s, %s, %s, %s, %s)',
        [(id_, CHECK_IMAGE, CHECK_IMAGE, reviewer, reviewed_at) for id_, reviewer, reviewed_at in sim['check_deposits']],
    )
    # A 1x1 PNG, so the employee review page has an image to show for simulated checks.
    image = config.STORAGE / 'uploads' / 'deposits' / CHECK_IMAGE
    image.parent.mkdir(parents=True, exist_ok=True)
    image.write_bytes(base64.b64decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='))
    return {'employees': len(STAFF), **{table: len(data) for table, data in sim.items()}}
