"""Getting in and out: customer login and registration, the emailed-code check, password and
username recovery, logout and the keep-alive ping."""
import re
import time
from decimal import Decimal

import pymysql
from flask import Blueprint, render_template, request, session

from . import banking, db
from .auth import (hash_password, login_as, logout as end_session, password_matches, password_problem,
                   redirect_if_logged_in, require_employee, require_user)
from .helpers import back_with, form, go, money
from .mail import send_mail
from .mfa import MFA_TTL, mfa_pending, mfa_send_code, mfa_start, mfa_verify
from .validation import PERSON_FIELDS, collect_input, password_errors, person_errors, ssn_fingerprint, valid_email

bp = Blueprint('access', __name__)

# Where "Back" (or a failed challenge) leads for each MFA purpose.
EXIT_PAGES = {
    'user_login': 'login',
    'employee_login': 'employee-login',
    'transfer': 'dashboard',
    'reset_user': 'forgot',
    'reset_employee': 'forgot?for=employee',
}


@bp.route('/login', methods=['GET', 'POST'])
def login():
    redirect_if_logged_in()
    if request.method == 'POST':
        user = db.one('SELECT id, email, password_hash FROM users WHERE username = %s', (form('username'),))
        if not password_matches(request.form.get('password', ''), user and user['password_hash']):
            back_with('login', 'Incorrect username or password.')
        mfa_start('user_login', user['email'], {'user_id': user['id']})
        go('mfa')
    return render_template('login.html')


@bp.route('/register', methods=['GET', 'POST'])
def register():
    # Customers register themselves; employees can also open this page to register a customer.
    if session.get('user_id'):
        go('dashboard')
    by_employee = bool(session.get('employee_id'))
    if by_employee:
        require_employee()

    old, errors = {}, []
    if request.method == 'POST':
        old = collect_input([*PERSON_FIELDS, 'username', 'email'])
        pin = request.form.get('pin', '')
        errors = person_errors(old) + password_errors()
        if not re.fullmatch(r'[A-Za-z0-9_.]{3,30}', old['username']):
            errors.append('Username must be 3-30 letters, numbers, dots or underscores.')
        if not valid_email(old['email']):
            errors.append('Enter a valid email address.')
        if not re.fullmatch(r'\d{4}', pin):
            errors.append('PIN must be 4 digits.')
        if pin != request.form.get('cpin', ''):
            errors.append('PINs do not match.')

        if not errors:
            ssn_hash = ssn_fingerprint(old['ssn'])
            values = (old['username'], old['email'], old['phone'], ssn_hash, old['lstate'], old['license'])
            # One query for every uniqueness check instead of five.
            taken = db.one(
                'SELECT MAX(username = %s) AS username, MAX(email = %s) AS email, MAX(phone = %s) AS phone,'
                '       MAX(ssn_hash = %s OR (license_state = %s AND license_number = %s)) AS identity'
                ' FROM users WHERE username = %s OR email = %s OR phone = %s OR ssn_hash = %s OR (license_state = %s AND license_number = %s)',
                values + values,
            )
            if taken['username']:
                errors.append('That username is already taken.')
            if taken['email']:
                errors.append('That email is already registered.')
            if taken['phone']:
                errors.append('That phone number is already registered.')
            if taken['identity']:
                errors.append('Registration unsuccessful. Please contact support.')

        if not errors:
            try:
                db.execute(
                    'INSERT INTO users (username, password_hash, pin_hash, first_name, last_name, email, phone, ssn_hash,'
                    ' license_state, license_number, address1, address2, city, state, zip)'
                    ' VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)',
                    (old['username'], hash_password(request.form['password']), hash_password(pin), old['first'], old['last'],
                     old['email'], old['phone'], ssn_hash, old['lstate'], old['license'], old['address'],
                     old['address2'] or None, old['city'], old['state'], old['zip']),
                )
            except pymysql.err.IntegrityError:
                errors.append('Some of those details are already registered.')  # lost a race with another signup
            else:
                if by_employee:
                    back_with('employee', f"Customer {old['username']} registered.", 'success')
                back_with('login', 'Registration successful. You can now log in.', 'success')

    return render_template('register.html', old=old, errors=errors, by_employee=by_employee)


@bp.route('/forgot', methods=['GET', 'POST'])
def forgot():
    redirect_if_logged_in()
    for_employee = form('for') == 'employee'
    login_page = 'employee-login' if for_employee else 'login'

    if request.method == 'POST':
        email = form('email')
        account = (db.one("SELECT id, email FROM employees WHERE email = %s AND status = 'Hired'", (email,)) if for_employee
                   else db.one('SELECT id, email, username FROM users WHERE email = %s', (email,)))

        if form('action') == 'reset':
            # Unknown emails get the same code screen (but no email), so this form can't be used to find customers.
            mfa_start('reset_employee' if for_employee else 'reset_user', account and account['email'], {'id': account and account['id']})
            go('mfa')

        # Recover username / employee id. Same response either way so emails can't be probed.
        if account and for_employee:
            send_mail(account['email'], 'Your Employee ID', f"Your Bank of the Future employee ID is: {account['id']}")
        elif account:
            send_mail(account['email'], 'Your Username', f"Your Bank of the Future username is: {account['username']}")
        back_with(login_page, f"If that email is registered, we sent you an email with your {'employee ID.' if for_employee else 'username.'}", 'info')

    return render_template('forgot.html', for_employee=for_employee, login_page=login_page)


@bp.route('/reset-password', methods=['GET', 'POST'])
def reset_password():
    # Reached only after verifying an emailed code on /mfa.
    reset = session.get('password_reset')
    if not reset or time.time() > reset['expires']:
        session.pop('password_reset', None)
        back_with('forgot', 'Your reset session expired. Please start again.')
    table = 'employees' if reset['table'] == 'employees' else 'users'
    login_page = 'employee-login' if table == 'employees' else 'login'

    if request.method == 'POST':
        password = request.form.get('password', '')
        if problem := password_problem(password):
            back_with('reset-password', problem)
        if password != request.form.get('cpassword', ''):
            back_with('reset-password', 'Passwords do not match.')
        db.execute(f'UPDATE {table} SET password_hash = %s WHERE id = %s', (hash_password(password), reset['id']))
        end_session('Password reset successful. You can now log in.', 'success')
        go(login_page)

    return render_template('reset-password.html')


@bp.route('/mfa', methods=['GET', 'POST'])
def mfa():
    pending = mfa_pending()
    if not pending:
        go('dashboard' if session.get('user_id') else 'employee' if session.get('employee_id') else 'login')
    exit_page = EXIT_PAGES[pending['purpose']]
    if pending['purpose'] == 'transfer':
        require_user()

    if 'cancel' in request.args:
        session.pop('mfa', None)
        go(exit_page)

    if request.method == 'POST' and form('action') == 'resend':
        if not mfa_send_code():
            back_with('mfa', 'Too many codes requested. Please go back and start again.')
        back_with('mfa', 'A new code was sent to your email.', 'info')

    if request.method == 'POST':
        verified = mfa_verify(form('code'))
        if not verified:
            if mfa_pending():
                back_with('mfa', 'The code you entered was invalid.')
            back_with(exit_page, 'That code expired or had too many wrong attempts. Please start again.')

        data, purpose = verified['data'], verified['purpose']
        if purpose == 'user_login':
            login_as('user', data['user_id'])
            banking.apply_interest(data['user_id'])
            go('dashboard')
        if purpose == 'employee_login':
            login_as('employee', data['employee_id'])
            banking.apply_interest()
            go('employee')
        if purpose == 'transfer':
            amount = Decimal(data['amount'])
            error = (banking.transfer_problem(data['user_id'], data['from'], data['to'], amount)
                     or banking.transfer(data['user_id'], data['from'], data['to'], amount))
            if error:
                back_with('dashboard', error)
            back_with('dashboard', f"Transferred {money(amount)} to account {data['to']}.", 'success')
        # reset_user / reset_employee
        session['password_reset'] = {
            'table': 'users' if purpose == 'reset_user' else 'employees',
            'id': data['id'],
            'expires': time.time() + MFA_TTL,
        }
        go('reset-password')

    return render_template('mfa.html', dev_code=pending.get('dev_code'))


@bp.route('/logout')
def logout():
    was_employee = bool(session.get('employee_id')) or (session.get('mfa') or {}).get('purpose') == 'employee_login'
    was_logged_in = bool(session.get('employee_id') or session.get('user_id'))
    end_session('You have been logged out.' if was_logged_in else None)
    go('employee-login' if was_employee else 'login' if was_logged_in else './')


@bp.route('/keepalive', methods=['POST'])
def keepalive():
    """Pinged by session-timeout.js while the user is active, to refresh the server-side inactivity timer."""
    require_employee() if session.get('employee_id') else require_user()
    return '', 204
