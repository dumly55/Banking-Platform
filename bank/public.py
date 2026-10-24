"""The public marketing site."""
from flask import Blueprint, render_template

bp = Blueprint('public', __name__)


@bp.route('/')
def index():
    return render_template('index.html')


@bp.route('/about')
def about():
    return render_template('about.html')


@bp.route('/services')
def services():
    return render_template('services.html')


@bp.route('/why-us')
def why_us():
    return render_template('why-us.html')
