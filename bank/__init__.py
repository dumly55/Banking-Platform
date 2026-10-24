"""Bank of the Future: an online banking web app (Python, Flask, MySQL).

    flask --app bank run --debug --port 8000     development server on http://localhost:8000
"""
import mimetypes

from flask import Flask

from . import config

# Windows' registry often lacks these, so images would be served as application/octet-stream.
mimetypes.add_type('image/webp', '.webp')
mimetypes.add_type('image/avif', '.avif')


def create_app():
    # The original app's CSS, JavaScript and images, served at /assets as before.
    app = Flask(__name__, static_folder=str(config.ROOT / 'public' / 'assets'), static_url_path='/assets')

    @app.after_request
    def security_headers(response):
        response.headers['X-Frame-Options'] = 'DENY'
        response.headers['X-Content-Type-Options'] = 'nosniff'
        response.headers['Referrer-Policy'] = 'same-origin'
        return response

    from . import db, public, setup
    app.teardown_appcontext(db.close)
    app.cli.add_command(setup.setup_db)
    app.register_blueprint(public.bp)
    return app
