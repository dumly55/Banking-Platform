"""Money movement. Every balance change runs inside a database transaction with the affected
account rows locked (SELECT ... FOR UPDATE), so concurrent requests can't double-spend."""
import secrets
from decimal import Decimal

from . import config, db
from .helpers import money, to_cents

ACCOUNT_TYPES = {
    # type: (card number prefix, annual interest rate)
    'Checking': ('4100', Decimal('0.0005')),
    'Savings': ('4200', Decimal('0.0057')),
}

DEPOSIT_IMAGE_TYPES = {'image/jpeg': 'jpg', 'image/png': 'png', 'image/gif': 'gif', 'image/webp': 'webp'}
DEPOSIT_MAX_BYTES = 5 * 1024 * 1024
DEPOSIT_UPLOADS = config.STORAGE / 'uploads' / 'deposits'


def user_accounts(user_id):
    return db.many('SELECT * FROM accounts WHERE user_id = %s ORDER BY created_at, account_number', (user_id,))


def user_account(user_id, account_number):
    return db.one('SELECT * FROM accounts WHERE account_number = %s AND user_id = %s', (account_number, user_id))


def is_own_account(user_id, account_number):
    return user_account(user_id, account_number) is not None


def open_account(user_id, kind):
    prefix, rate = ACCOUNT_TYPES[kind]
    number = db.unique_number('accounts', 'account_number', 16, prefix)
    db.execute(
        'INSERT INTO accounts (account_number, user_id, type, interest_rate, interest_applied_on) VALUES (%s, %s, %s, %s, CURDATE())',
        (number, user_id, kind, rate),
    )
    return number


def record_transaction(account, kind, status, amount, before, description, related=None, transaction_id=None):
    transaction_id = transaction_id or db.unique_number('transactions', 'id', 12)
    db.execute(
        'INSERT INTO transactions (id, account_number, type, status, amount, balance_before, description, related_transaction)'
        ' VALUES (%s, %s, %s, %s, %s, %s, %s, %s)',
        (transaction_id, account, kind, status, amount, before, description, related),
    )
    return transaction_id


def apply_interest(user_id=None):
    """Pays yearly compound interest on every anniversary that has passed. Pass a user id to limit it to their accounts."""
    where = 'interest_applied_on <= CURDATE() - INTERVAL 1 YEAR' + (' AND user_id = %s' if user_id else '')
    due = db.many(f'SELECT account_number FROM accounts WHERE {where}', (user_id,) if user_id else ())

    for row in due:
        number = row['account_number']
        with db.transaction():
            account = db.one(
                'SELECT balance, interest_rate, TIMESTAMPDIFF(YEAR, interest_applied_on, CURDATE()) AS years'
                ' FROM accounts WHERE account_number = %s FOR UPDATE',
                (number,),
            )
            if account['years'] < 1:
                continue
            new_balance = to_cents(account['balance'] * (1 + account['interest_rate']) ** account['years'])
            db.execute(
                'UPDATE accounts SET balance = %s, interest_applied_on = interest_applied_on + INTERVAL %s YEAR WHERE account_number = %s',
                (new_balance, account['years'], number),
            )
            interest = new_balance - account['balance']
            if interest > 0:
                record_transaction(number, 'Interest', 'Processed', interest, account['balance'], 'Interest payment')


def lock_accounts(*numbers):
    """Locks the given accounts in a fixed order (avoids deadlocks) and returns them keyed by number."""
    numbers = sorted(numbers)
    placeholders = ', '.join(['%s'] * len(numbers))
    rows = db.many(f'SELECT * FROM accounts WHERE account_number IN ({placeholders}) ORDER BY account_number FOR UPDATE', numbers)
    return {row['account_number']: row for row in rows}


def transfer_problem(user_id, source, target, amount):
    """Returns an error message, or None when the transfer is valid. Used before and after MFA."""
    if amount is None:
        return 'Enter a valid amount greater than $0.00.'
    if source == target:
        return 'You cannot transfer money to and from the same account.'
    if not user_account(user_id, source):
        return 'Choose one of your own accounts to transfer from.'
    if not db.value('SELECT 1 FROM accounts WHERE account_number = %s', (target,)):
        return 'Recipient account does not exist.'
    return None


def transfer(user_id, source, target, amount):
    """Moves money between accounts. Returns an error message, or None on success."""
    with db.transaction():
        accounts = lock_accounts(source, target)
        sender, recipient = accounts.get(source), accounts.get(target)
        if not sender or sender['user_id'] != user_id or not recipient or source == target:
            return 'That transfer is no longer valid.'
        if sender['balance'] < amount:
            return f"Insufficient funds: your balance is {money(sender['balance'])}."

        db.execute('UPDATE accounts SET balance = balance - %s WHERE account_number = %s', (amount, source))
        db.execute('UPDATE accounts SET balance = balance + %s WHERE account_number = %s', (amount, target))
        out_id = db.unique_number('transactions', 'id', 12)
        in_id = db.unique_number('transactions', 'id', 12)
        # Statements show only the last 4 digits of the other account, like a real bank.
        record_transaction(source, 'Transfer Out', 'Processed', -amount, sender['balance'], f'Transfer to account ending {target[-4:]}', in_id, out_id)
        record_transaction(target, 'Transfer In', 'Processed', amount, recipient['balance'], f'Transfer from account ending {source[-4:]}', out_id, in_id)
    return None


def withdraw(user_id, account_number, amount):
    """ATM withdrawal. Returns an error message, or None on success."""
    with db.transaction():
        account = lock_accounts(account_number).get(account_number)
        if not account or account['user_id'] != user_id:
            return 'Account not found.'
        if account['balance'] < amount:
            return f"Insufficient funds: your balance is {money(account['balance'])}."
        db.execute('UPDATE accounts SET balance = balance - %s WHERE account_number = %s', (amount, account_number))
        record_transaction(account_number, 'Withdrawal', 'Processed', -amount, account['balance'], 'ATM withdrawal')
    return None


def image_type(head):
    """Identifies an image by its first bytes (as PHP's finfo did), so a renamed file can't pass."""
    if head.startswith(b'\xff\xd8\xff'):
        return 'image/jpeg'
    if head.startswith(b'\x89PNG\r\n\x1a\n'):
        return 'image/png'
    if head[:6] in (b'GIF87a', b'GIF89a'):
        return 'image/gif'
    if head[:4] == b'RIFF' and head[8:12] == b'WEBP':
        return 'image/webp'
    return None


def store_check_image(upload):
    """Validates an uploaded check image and stores it under a random name. Returns (file name, error)."""
    if not upload or not upload.filename:
        return None, 'Please upload both sides of the check.'
    data = upload.read(DEPOSIT_MAX_BYTES + 1)
    if len(data) > DEPOSIT_MAX_BYTES:
        return None, 'Check images must be 5 MB or smaller.'
    extension = DEPOSIT_IMAGE_TYPES.get(image_type(data[:12]))
    if not extension:
        return None, 'Check images must be JPG, PNG, GIF or WebP files.'
    name = f'{secrets.token_hex(16)}.{extension}'
    DEPOSIT_UPLOADS.mkdir(parents=True, exist_ok=True)
    (DEPOSIT_UPLOADS / name).write_bytes(data)
    return name, None


def create_deposit(user_id, account_number, amount, front, back):
    """Creates a pending check deposit for an employee to review. Returns an error message, or None."""
    if not user_account(user_id, account_number):
        return 'Choose one of your accounts to deposit into.'
    front_name, error = store_check_image(front)
    if error:
        return error
    back_name, error = store_check_image(back)
    if error:
        (DEPOSIT_UPLOADS / front_name).unlink(missing_ok=True)
        return error
    with db.transaction():
        transaction_id = record_transaction(account_number, 'Deposit', 'Pending', amount, None, 'Mobile check deposit')
        db.execute('INSERT INTO check_deposits (transaction_id, front_image, back_image) VALUES (%s, %s, %s)',
                   (transaction_id, front_name, back_name))
    return None


def review_deposit(employee_id, transaction_id, approve):
    """Approves or denies a pending check deposit. Returns False if it was already reviewed."""
    with db.transaction():
        tx = db.one(
            "SELECT * FROM transactions WHERE id = %s AND type = 'Deposit' AND status = 'Pending' FOR UPDATE",
            (transaction_id,),
        )
        if not tx:
            return False
        account = lock_accounts(tx['account_number'])[tx['account_number']]
        if approve:
            db.execute('UPDATE accounts SET balance = balance + %s WHERE account_number = %s', (tx['amount'], tx['account_number']))
        db.execute(
            'UPDATE transactions SET status = %s, balance_before = %s WHERE id = %s',
            ('Processed' if approve else 'Denied', account['balance'], transaction_id),
        )
        db.execute('UPDATE check_deposits SET reviewed_by = %s, reviewed_at = NOW() WHERE transaction_id = %s', (employee_id, transaction_id))
    return True
