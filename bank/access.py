"""Getting in and out: customer login, the emailed-code check, logout and the keep-alive ping."""
import time
from decimal import Decimal

from flask import Blueprint, render_template, request, session

from . import banking, db
from .auth import login_as, logout as end_session, password_matches, redirect_if_logged_in, require_employee, require_user
from .helpers import back_with, form, go, money
from .mfa import MFA_TTL, mfa_pending, mfa_send_code, mfa_start, mfa_verify

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
@bp.route('/logout.php')  # shortcut: session-timeout.js still calls the PHP name; renamed when PHP is removed
def logout():
    was_employee = bool(session.get('employee_id')) or (session.get('mfa') or {}).get('purpose') == 'employee_login'
    was_logged_in = bool(session.get('employee_id') or session.get('user_id'))
    end_session('You have been logged out.' if was_logged_in else None)
    go('employee-login' if was_employee else 'login' if was_logged_in else './')


@bp.route('/keepalive', methods=['POST'])
@bp.route('/keepalive.php', methods=['POST'])  # shortcut: as above
def keepalive():
    """Pinged by session-timeout.js while the user is active, to refresh the server-side inactivity timer."""
    require_employee() if session.get('employee_id') else require_user()
    return '', 204
