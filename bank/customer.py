"""The customer side: accounts, transfers, the ATM and check deposits."""
from flask import Blueprint, render_template, request, session

from . import banking, db
from .auth import logout, password_matches, require_user
from .helpers import back_with, form, go, money, parse_amount
from .mfa import mfa_start

bp = Blueprint('customer', __name__)

MAX_PIN_FAILURES = 5


@bp.route('/dashboard')
def dashboard():
    user_id = require_user()
    first_name = db.value('SELECT first_name FROM users WHERE id = %s', (user_id,))
    return render_template('dashboard.html', first_name=first_name, accounts=banking.user_accounts(user_id))


@bp.route('/open-account', methods=['GET', 'POST'])
def open_account():
    user_id = require_user()
    if request.method != 'POST':
        go('dashboard')
    kind = form('type')
    if kind not in banking.ACCOUNT_TYPES:
        back_with('dashboard', 'Choose an account type.')
    number = banking.open_account(user_id, kind)
    back_with('dashboard', f'Your new {kind} account (#{number}) is open.', 'success')


@bp.route('/account')
def account():
    user_id = require_user()
    account = banking.user_account(user_id, form('number'))
    if not account:
        back_with('dashboard', 'Account not found.')
    transactions = db.many('SELECT * FROM transactions WHERE account_number = %s ORDER BY created_at DESC, id DESC',
                           (account['account_number'],))
    return render_template('account.html', account=account, transactions=transactions)


@bp.route('/transfer', methods=['GET', 'POST'])
def transfer():
    user_id = require_user()
    if request.method != 'POST':
        go('dashboard')
    source, target, amount = form('from'), form('to'), parse_amount(form('amount'))
    error = banking.transfer_problem(user_id, source, target, amount)
    if error:
        back_with('dashboard', error)

    # Moving money between your own accounts happens right away; sending to someone else needs an emailed code.
    if banking.is_own_account(user_id, target):
        error = banking.transfer(user_id, source, target, amount)
        if error:
            back_with('dashboard', error)
        back_with('dashboard', f'Transferred {money(amount)} to account {target}.', 'success')

    email = db.value('SELECT email FROM users WHERE id = %s', (user_id,))
    mfa_start('transfer', email, {'user_id': user_id, 'from': source, 'to': target, 'amount': str(amount)})
    go('mfa')


@bp.route('/atm', methods=['GET', 'POST'])
def atm():
    user_id = require_user()
    if request.method == 'POST':
        pin_hash = db.value('SELECT pin_hash FROM users WHERE id = %s', (user_id,))
        if not password_matches(request.form.get('pin', ''), pin_hash):
            session['pin_failures'] = session.get('pin_failures', 0) + 1
            if session['pin_failures'] >= MAX_PIN_FAILURES:
                logout('Too many incorrect PIN attempts. Please log in again.', 'error')
                go('login')
            back_with('atm', 'Incorrect PIN.')
        session.pop('pin_failures', None)

        amount = parse_amount(form('amount'))
        if not amount:
            back_with('atm', 'Enter a valid amount greater than $0.00.')
        error = banking.withdraw(user_id, form('account'), amount)
        if error:
            back_with('atm', error)
        back_with('atm', f'Withdrawal of {money(amount)} successful.', 'success')

    user = db.one('SELECT first_name, last_name FROM users WHERE id = %s', (user_id,))
    return render_template('atm.html', user=user, accounts=banking.user_accounts(user_id))


@bp.route('/deposit', methods=['GET', 'POST'])
def deposit():
    user_id = require_user()
    if request.method == 'POST':
        amount = parse_amount(form('amount'))
        if not amount:
            back_with('deposit', 'Enter a valid amount greater than $0.00.')
        error = banking.create_deposit(user_id, form('account'), amount,
                                       request.files.get('frontCheck'), request.files.get('backCheck'))
        if error:
            back_with('deposit', error)
        back_with('deposit', f'Your deposit of {money(amount)} is being processed. It will appear in your balance once approved.', 'success')
    return render_template('deposit.html', accounts=banking.user_accounts(user_id))
