"""The customer side: accounts, transfers, the ATM and check deposits."""
from flask import Blueprint, render_template

from . import banking, db
from .auth import require_user

bp = Blueprint('customer', __name__)


@bp.route('/dashboard')
def dashboard():
    user_id = require_user()
    first_name = db.value('SELECT first_name FROM users WHERE id = %s', (user_id,))
    return render_template('dashboard.html', first_name=first_name, accounts=banking.user_accounts(user_id))
