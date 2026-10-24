"""Passwords, sessions and access checks.

Session state:
    session['user_id']      logged-in customer
    session['employee_id']  logged-in employee
    session['last_seen']    time of the last request, for the inactivity timeout
    session['mfa']          a pending multi-factor challenge (see mfa.py)
"""
import re
import time

import bcrypt
from flask import current_app, flash, session

from . import config, db
from .helpers import back_with, go

# ---- Passwords and PINs. bcrypt hashes are compatible with the PHP app's password_hash(). ----


def _bytes(secret):
    return secret.encode()[:72]  # bcrypt only uses the first 72 bytes; PHP truncated silently too


def hash_password(secret):
    return bcrypt.hashpw(_bytes(secret), bcrypt.gensalt()).decode()


_DUMMY_HASH = hash_password('not a real password')


def password_matches(password, hashed):
    """Checks a password against a stored hash, spending the same time when there is no account."""
    return bcrypt.checkpw(_bytes(password), (hashed or _DUMMY_HASH).encode()) and hashed is not None


def password_problem(password):
    """Password rules shared by customer and employee forms: 8+ characters with a letter and a number."""
    if len(password) < 8 or not re.search(r'[A-Za-z]', password) or not re.search(r'\d', password):
        return 'Password must be at least 8 characters and contain a letter and a number.'
    return None


# ---- Sessions ----

def login_as(role, id_):
    for key in ('mfa', 'user_id', 'employee_id'):
        session.pop(key, None)
    session['employee_id' if role == 'employee' else 'user_id'] = id_
    session['last_seen'] = time.time()
    current_app.session_interface.regenerate(session)  # new session id, so a planted one can't be reused


def logout(message=None, kind='info'):
    session.clear()
    if message:
        flash(message, kind)
        current_app.session_interface.regenerate(session)


def enforce_timeout(login_page):
    """Ends the session after SESSION_TIMEOUT seconds without a request."""
    if time.time() - session.get('last_seen', 0) > config.SESSION_TIMEOUT:
        logout('You were logged out due to inactivity.')
        go(login_page)
    session['last_seen'] = time.time()


def require_user():
    """Returns the logged-in customer's id, or redirects to the login page."""
    if session.get('employee_id'):
        go('employee')
    if not session.get('user_id'):
        go('login')
    enforce_timeout('login')
    return session['user_id']


def require_employee(min_tier=1):
    """Returns the logged-in employee's row, or redirects. Status and tier are re-read from the
    database on every request so terminations and tier changes take effect immediately."""
    if session.get('user_id'):
        go('dashboard')
    if not session.get('employee_id'):
        go('employee-login')
    enforce_timeout('employee-login')

    employee = db.one('SELECT * FROM employees WHERE id = %s', (session['employee_id'],))
    if not employee or employee['status'] != 'Hired':
        logout('Your employee access has been revoked.', 'error')
        go('employee-login')
    if employee['tier'] < min_tier:
        back_with('employee', 'You do not have permission to do that.')
    return employee


def redirect_if_logged_in():
    """Sends already-logged-in visitors to their home page (for login/registration pages)."""
    if session.get('user_id'):
        go('dashboard')
    if session.get('employee_id'):
        go('employee')
