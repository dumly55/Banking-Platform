"""Money formatting and parsing shared by pages and banking code."""
import re
from decimal import ROUND_HALF_UP, Decimal

CENT = Decimal('0.01')


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
