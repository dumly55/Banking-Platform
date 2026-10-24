"""Email-based one-time codes. A challenge is started for a purpose (logging in, an external
transfer, or a password reset) and the action runs only after the code is verified on /mfa."""
import re
import secrets
import time

from flask import session

from . import config
from .auth import hash_password, password_matches
from .mail import mail_is_logged, send_mail

MFA_TTL = 600         # seconds a code stays valid
MFA_MAX_ATTEMPTS = 5  # wrong guesses before the challenge is discarded
MFA_MAX_SENDS = 4     # first code plus 3 resends


def mfa_start(purpose, email, data):
    """purpose: user_login | employee_login | transfer | reset_user | reset_employee.
    email None starts a decoy challenge that sends nothing and can never pass, so forms like
    password reset look the same for unknown emails. data is what the purpose needs once verified."""
    session['mfa'] = {'purpose': purpose, 'email': email, 'data': data}
    mfa_send_code()


def mfa_pending():
    return session.get('mfa')


def mfa_send_code():
    """Emails a fresh code. Returns False once the resend limit is reached."""
    mfa = session['mfa']
    if mfa.get('sends', 0) >= MFA_MAX_SENDS:
        return False
    code = f'{secrets.randbelow(1_000_000):06d}'
    mfa.update(sends=mfa.get('sends', 0) + 1, hash=hash_password(code), expires=time.time() + MFA_TTL, attempts=0)
    session.modified = True  # the change is inside a nested dict

    if mfa['email'] is None:
        return True  # decoy: nothing to send
    # Without real email, show the code on the MFA page so the app is usable locally.
    if mail_is_logged() and config.ENV != 'production':
        mfa['dev_code'] = code
    send_mail(
        mfa['email'],
        'Your Bank of the Future verification code',
        f'Your verification code is: {code}\n\nIt expires in {MFA_TTL // 60} minutes. If you did not request it, you can ignore this email.',
    )
    return True


def mfa_verify(code):
    """Returns the pending challenge if code is correct; otherwise None (and counts the attempt)."""
    mfa = mfa_pending()
    if not mfa:
        return None
    if time.time() > mfa['expires'] or mfa['attempts'] >= MFA_MAX_ATTEMPTS:
        session.pop('mfa')
        return None
    # A decoy has no account behind it, so it can never pass.
    if not re.fullmatch(r'\d{6}', code) or mfa['email'] is None or not password_matches(code, mfa['hash']):
        mfa['attempts'] += 1
        session.modified = True
        return None
    return session.pop('mfa')
