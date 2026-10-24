"""Validation for the personal details collected from customers and employees."""
import hashlib
import hmac

from . import config


def ssn_fingerprint(ssn):
    """SSNs are only needed to stop duplicate sign-ups, so only a keyed hash is stored.
    Without the key, the 1 billion possible SSNs can't be brute-forced back from it.
    Same HMAC-SHA256 as the PHP app, so existing fingerprints still match."""
    if not config.KEY:
        raise RuntimeError('APP_KEY is not set. Run: flask --app bank setup-db')
    return hmac.new(config.KEY.encode(), ssn.encode(), hashlib.sha256).hexdigest()
