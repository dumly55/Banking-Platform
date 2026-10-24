"""Bank of the Future: an online banking web app (Python, Flask, MySQL).

    flask --app bank run --debug --port 8000     development server on http://localhost:8000
"""
import mimetypes
from datetime import timedelta

from cachelib.file import FileSystemCache
from flask import Flask, request
from flask_session import Session

from . import config
from .helpers import csrf_field, format_card_number, money, us_datetime, verify_csrf

# Windows' registry often lacks these, so images would be served as application/octet-stream.
mimetypes.add_type('image/webp', '.webp')
mimetypes.add_type('image/avif', '.avif')


def create_app():
    # The original app's CSS, JavaScript and images, served at /assets as before.
    app = Flask(__name__, static_folder=str(config.ROOT / 'public' / 'assets'), static_url_path='/assets')

    # Sessions live on the server (storage/sessions), like PHP's: the browser only holds a random id,
    # never the data, because the session carries things like the pending MFA code's hash.
    app.config.update(
        SECRET_KEY=config.KEY,
        SESSION_TYPE='cachelib',
        SESSION_CACHELIB=FileSystemCache(str(config.STORAGE / 'sessions'), threshold=10_000),
        SESSION_PERMANENT=False,
        PERMANENT_SESSION_LIFETIME=timedelta(hours=1),  # how long idle session files are kept
        SESSION_COOKIE_SAMESITE='Lax',
        SESSION_COOKIE_SECURE=config.URL.startswith('https://'),
        MAX_CONTENT_LENGTH=11 * 1024 * 1024,  # two 5 MB check images plus the form; bigger requests are refused
    )
    Session(app)

    app.jinja_env.globals['csrf_field'] = csrf_field
    app.jinja_env.filters.update(money=money, card=format_card_number, us_datetime=us_datetime)

    @app.before_request
    def check_csrf():
        # Every form that changes state carries a token. The keep-alive ping only refreshes the timer.
        if request.method == 'POST' and request.endpoint != 'access.keepalive':
            verify_csrf()

    @app.after_request
    def security_headers(response):
        response.headers['X-Frame-Options'] = 'DENY'
        response.headers['X-Content-Type-Options'] = 'nosniff'
        response.headers['Referrer-Policy'] = 'same-origin'
        return response

    from . import access, customer, db, public, setup
    app.teardown_appcontext(db.close)
    app.cli.add_command(setup.setup_db)
    for blueprint in (public.bp, access.bp, customer.bp):
        app.register_blueprint(blueprint)
    return app
