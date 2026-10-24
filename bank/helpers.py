"""Money formatting, form input, flash-and-redirect, and CSRF protection."""
import hmac
import re
import secrets
from decimal import ROUND_HALF_UP, Decimal

from flask import abort, flash, make_response, redirect, request, session
from markupsafe import Markup, escape

CENT = Decimal('0.01')


# ---- Money ----

def to_cents(amount):
    """Rounds to the cent, halves away from zero (like PHP's round)."""
    return Decimal(amount).quantize(CENT, rounding=ROUND_HALF_UP)


def parse_amount(raw):
    """Parses a positive dollar amount with at most 2 decimals, or returns None."""
    if not re.fullmatch(r'\d{1,9}(\.\d{1,2})?', raw) or Decimal(raw) <= 0:
        return None
    return to_cents(raw)


def money(amount):
    amount = Decimal(amount)
    return f"{'-' if amount < 0 else ''}${abs(amount):,.2f}"


def format_card_number(number):
    return ' '.join(number[i:i + 4] for i in range(0, len(number), 4))


def nl2br(text):
    """Escapes text and keeps its line breaks (like PHP's nl2br(e(...)))."""
    return escape(text).replace('\n', Markup('<br>\n'))


def us_datetime(value, seconds=False, separator=' '):
    """03/14/2026 2:05 PM, like the PHP app's date('m/d/Y g:i A')."""
    hour = value.hour % 12 or 12
    clock = f"{hour}:{value:%M}{value.strftime(':%S') if seconds else ''} {'AM' if value.hour < 12 else 'PM'}"
    return f'{value:%m/%d/%Y}{separator}{clock}'


# ---- Requests ----

def form(key, default=''):
    """A trimmed form value (POST first, then the query string)."""
    value = request.form.get(key, request.args.get(key, default))
    return value.strip()


def back_with(url, message, kind='error'):
    """Flashes a message and redirects: the usual way to end a POST handler. Stops the request."""
    flash(message, kind)
    abort(redirect(url))


def go(url):
    """Redirects from anywhere, including helpers called by a page. Stops the request."""
    abort(redirect(url))


# ---- CSRF protection for every form that changes state ----

def csrf_token():
    if 'csrf' not in session:
        session['csrf'] = secrets.token_hex(32)
    return session['csrf']


def csrf_field():
    return Markup(f'<input type="hidden" name="csrf" value="{csrf_token()}">')


def verify_csrf():
    if not hmac.compare_digest(csrf_token(), request.form.get('csrf', '')):
        abort(make_response('Your session expired. Please go back and try again.', 419))
