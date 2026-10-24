"""Settings come from environment variables, optionally loaded from a .env file in the project root."""
import os
from pathlib import Path

from dotenv import load_dotenv

ROOT = Path(__file__).resolve().parent.parent
load_dotenv(ROOT / '.env')  # real environment variables take precedence


def env(key, default=None):
    value = os.environ.get(key)
    return default if value is None or value == '' else value


# Defaults to production so a server without a .env never shows errors or on-screen MFA codes.
ENV = env('APP_ENV', 'production')
URL = env('APP_URL', 'http://localhost:8000').rstrip('/')
TIMEZONE = env('APP_TIMEZONE', 'America/Los_Angeles')
# Secret used to fingerprint SSNs and sign sessions. `flask setup-db` generates it; changing it breaks duplicate checks.
KEY = env('APP_KEY')
SESSION_TIMEOUT = int(env('SESSION_TIMEOUT', '600'))

DB = {
    'host': env('DB_HOST', '127.0.0.1'),
    'port': int(env('DB_PORT', '3306')),
    'name': env('DB_NAME', 'bank_of_the_future'),
    'user': env('DB_USER', 'root'),
    'password': env('DB_PASS', ''),
}

# Leave SMTP_HOST empty to write outgoing mail to storage/logs/mail.log instead of sending it.
MAIL = {
    'host': env('SMTP_HOST'),
    'port': int(env('SMTP_PORT', '587')),
    'user': env('SMTP_USER'),
    'password': env('SMTP_PASS'),
    'from': env('MAIL_FROM', 'no-reply@bankofthefuture.test'),
    'from_name': env('MAIL_FROM_NAME', 'Bank of the Future'),
}

STORAGE = ROOT / 'storage'
