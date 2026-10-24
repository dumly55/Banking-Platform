"""The public site: marketing pages and the contact form."""
from flask import Blueprint, render_template, request

from . import db
from .helpers import back_with, form
from .mail import send_mail
from .validation import valid_email

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


@bp.route('/contact', methods=['GET', 'POST'])
def contact():
    if request.method == 'POST':
        name, email, question = form('name'), form('email'), form('question')
        if not name or len(name) > 100:
            back_with('contact', 'Please enter your name.')
        if not valid_email(email):
            back_with('contact', 'Please enter a valid email address.')
        if not question or len(question) > 500:
            back_with('contact', 'Your question must be 1-500 characters.')

        ticket = db.unique_number('support_tickets', 'id', 10)
        db.execute('INSERT INTO support_tickets (id, name, email, question) VALUES (%s, %s, %s, %s)', (ticket, name, email, question))
        # Only fixed text goes out: echoing the visitor's name/question would let anyone send
        # their own message to any address from the bank's mail account.
        send_mail(
            email,
            f'Ticket #{ticket} (Bank of the Future Support Team)',
            f'We received your question and created ticket #{ticket}. We will get back to you as soon as we can.\n\n'
            'If you did not contact Bank of the Future, you can ignore this email.\n\nSincerely,\nBank of the Future Team',
        )
        back_with('contact', f'Ticket #{ticket} submitted. A confirmation was sent to your email.', 'success')
    return render_template('contact.html')
