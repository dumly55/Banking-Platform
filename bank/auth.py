"""Passwords and PINs. bcrypt hashes are compatible with the PHP app's password_hash()."""
import re

import bcrypt


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
