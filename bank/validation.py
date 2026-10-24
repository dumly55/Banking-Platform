"""Validation for the personal details collected from customers and employees."""
import hashlib
import hmac
import re

from flask import request

from . import config
from .auth import password_problem
from .helpers import form

PERSON_FIELDS = ['first', 'last', 'ssn', 'phone', 'lstate', 'license', 'address', 'address2', 'city', 'state', 'zip']

PERSON_RULES = {
    'first': (r'.{1,50}', 'Enter your first name.'),
    'last': (r'.{1,50}', 'Enter your last name.'),
    'ssn': (r'\d{9}', 'Social Security Number must be 9 digits.'),
    'phone': (r'\d{10}', 'Phone number must be 10 digits.'),
    'lstate': (r'[A-Z]{2}', 'License state must be a 2-letter code.'),
    'license': (r'[A-Za-z0-9]{1,20}', 'License number must be 1-20 letters or numbers.'),
    'address': (r'.{1,100}', 'Enter your street address.'),
    'address2': (r'.{0,100}', 'Address line 2 is too long.'),
    'city': (r"[A-Za-z ,.'-]{1,60}", 'Enter a valid city.'),
    'state': (r'[A-Z]{2}', 'State must be a 2-letter code.'),
    'zip': (r'\d{5}', 'Zip code must be 5 digits.'),
}

EMAIL = re.compile(r"[A-Za-z0-9.!#$%&'*+/=?^_`{|}~-]+@[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)+")


def valid_email(value):
    return len(value) <= 254 and EMAIL.fullmatch(value) is not None


def ssn_fingerprint(ssn):
    """SSNs are only needed to stop duplicate sign-ups, so only a keyed hash is stored.
    Without the key, the 1 billion possible SSNs can't be brute-forced back from it.
    Same HMAC-SHA256 as the PHP app, so existing fingerprints still match."""
    if not config.KEY:
        raise RuntimeError('APP_KEY is not set. Run: flask --app bank setup-db')
    return hmac.new(config.KEY.encode(), ssn.encode(), hashlib.sha256).hexdigest()


def collect_input(fields):
    """Reads the given fields from the submitted form, trimmed."""
    return {field: form(field) for field in fields}


def person_errors(values):
    """Error messages for any person field in values that breaks PERSON_RULES."""
    return [message for field, (pattern, message) in PERSON_RULES.items()
            if not re.fullmatch(pattern, values.get(field, ''))]


def password_errors():
    """Validates the submitted password and its confirmation."""
    password = request.form.get('password', '')
    errors = []
    if problem := password_problem(password):
        errors.append(problem)
    if password != request.form.get('cpassword', ''):
        errors.append('Passwords do not match.')
    return errors
