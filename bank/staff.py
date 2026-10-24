"""The employee back office: login, new-hire registration, the dashboard with hiring and termination,
check deposit review and support inquiries."""
from flask import Blueprint, abort, render_template, request, send_from_directory, session

from . import banking, config, db
from .auth import hash_password, password_matches, redirect_if_logged_in, require_employee
from .helpers import back_with, form, go
from .mail import send_mail
from .mfa import mfa_start
from .validation import PERSON_FIELDS, collect_input, password_errors, person_errors, ssn_fingerprint, valid_email

bp = Blueprint('staff', __name__)

# The on-screen keyboard on the employee login page.
KEYS = [*'1234567890abcdefghijklmnopqrstuvwxyz', '^', '-', '_', '@', '+', '&', '*', '%', '$', '#', '!']


def render_staff(template, me, title, current, **context):
    """Renders an employee page with the nav bar's live counts."""
    return render_template(
        template, me=me, title=title, current=current,
        pending_deposits=db.value("SELECT COUNT(*) FROM transactions WHERE type = 'Deposit' AND status = 'Pending'"),
        open_tickets=db.value("SELECT COUNT(*) FROM support_tickets WHERE status = 'Open'"),
        **context,
    )


def as_int(value):
    return int(value) if value.isdigit() else 0


@bp.route('/employee-login', methods=['GET', 'POST'])
def employee_login():
    redirect_if_logged_in()
    if request.method == 'POST':
        employee = db.one('SELECT id, email, password_hash, status FROM employees WHERE id = %s', (form('employee_id'),))
        if not password_matches(request.form.get('password', ''), employee and employee['password_hash']):
            back_with('employee-login', 'Incorrect employee ID or password.')
        # Only tell someone they were terminated once they've proven who they are.
        if employee['status'] != 'Hired':
            back_with('employee-login', 'Sorry. You are no longer with the company.')
        mfa_start('employee_login', employee['email'], {'employee_id': employee['id']})
        go('mfa')
    return render_template('employee-login.html', keys=KEYS)


@bp.route('/employee-register', methods=['GET', 'POST'])
def employee_register():
    """New hires register in two steps: confirm the employee ID from their offer email, then fill in their details."""
    redirect_if_logged_in()
    if 'cancel' in request.args:
        session.pop('employee_signup', None)
        go('employee-login')

    signup_id = session.get('employee_signup')
    old, errors = {}, []

    if request.method == 'POST' and form('step') == 'verify':
        found = db.value(
            "SELECT id FROM employees WHERE id = %s AND email = %s AND status = 'Pending' AND password_hash IS NULL",
            (form('employee_id'), form('email')),
        )
        if not found:
            back_with('employee-register', 'No pending offer matches that employee ID and email.')
        session['employee_signup'] = found
        go('employee-register')

    if request.method == 'POST' and signup_id:
        old = collect_input(PERSON_FIELDS)
        errors = person_errors(old) + password_errors()
        if not errors and db.value(
            'SELECT 1 FROM employees WHERE id <> %s AND (ssn_hash = %s OR phone = %s OR (license_state = %s AND license_number = %s))',
            (signup_id, ssn_fingerprint(old['ssn']), old['phone'], old['lstate'], old['license']),
        ):
            errors.append('Registration unsuccessful: those details belong to another employee.')

        if not errors:
            db.execute(
                "UPDATE employees SET password_hash = %s, status = 'Hired', first_name = %s, last_name = %s, phone = %s, ssn_hash = %s,"
                " license_state = %s, license_number = %s, address1 = %s, address2 = %s, city = %s, state = %s, zip = %s"
                " WHERE id = %s AND status = 'Pending'",
                (hash_password(request.form['password']), old['first'], old['last'], old['phone'], ssn_fingerprint(old['ssn']),
                 old['lstate'], old['license'], old['address'], old['address2'] or None, old['city'], old['state'], old['zip'],
                 signup_id),
            )
            session.pop('employee_signup', None)
            back_with('employee-login', f'Registration successful. Log in with employee ID {signup_id}.', 'success')

    return render_template('employee-register.html', signup_id=signup_id, old=old, errors=errors)


def terminable_employees(me):
    """Employees me may terminate: admins (tier 3) manage everyone below them, managers only their own hires."""
    if me['tier'] >= 3:
        return db.many("SELECT * FROM employees WHERE tier < %s AND status <> 'Terminated' ORDER BY tier DESC, last_name", (me['tier'],))
    return db.many("SELECT * FROM employees WHERE manager_id = %s AND tier < %s AND status <> 'Terminated' ORDER BY last_name",
                   (me['id'], me['tier']))


@bp.route('/employee', methods=['GET', 'POST'])
def employee():
    me = require_employee()

    if request.method == 'POST':
        me = require_employee(2)

        if form('action') == 'hire':
            email, tier = form('email'), as_int(form('tier'))
            if not valid_email(email):
                back_with('employee', 'Enter a valid email address.')
            if tier < 1 or tier >= me['tier']:
                back_with('employee', 'You can only hire into tiers below your own.')

            existing = db.one('SELECT id, status, password_hash FROM employees WHERE email = %s', (email,))
            if existing and existing['status'] != 'Terminated':
                back_with('employee', 'That person already works at Bank of the Future.' if existing['status'] == 'Hired'
                          else f"That person already has a pending offer (employee ID {existing['id']}).")

            if existing:
                # Rehire: people who registered before can log straight back in.
                id_ = existing['id']
                status = 'Hired' if existing['password_hash'] else 'Pending'
                db.execute('UPDATE employees SET status = %s, tier = %s, manager_id = %s WHERE id = %s', (status, tier, me['id'], id_))
                body = (f'Welcome back to Bank of the Future! You can log in again with your employee ID ({id_}) and existing password.'
                        if status == 'Hired' else
                        f'Welcome back to Bank of the Future! Use your employee ID ({id_}) to register at {config.URL}/employee-register')
            else:
                id_ = db.unique_number('employees', 'id', 9)
                db.execute("INSERT INTO employees (id, email, status, tier, manager_id) VALUES (%s, %s, 'Pending', %s, %s)",
                           (id_, email, tier, me['id']))
                body = (f'Congratulations on joining Bank of the Future! Your employee ID is {id_}.\n\n'
                        f'Use it at {config.URL}/employee-register to set up your employee account.')
            send_mail(email, 'Job Offer from Bank of the Future', body)
            back_with('employee', f'Offer sent to {email} (employee ID {id_}).', 'success')

        if form('action') == 'terminate':
            target = as_int(form('employee'))
            if target not in {e['id'] for e in terminable_employees(me)}:
                back_with('employee', 'You cannot terminate that employee.')
            db.execute("UPDATE employees SET status = 'Terminated' WHERE id = %s", (target,))
            back_with('employee', f'Employee {target} has been terminated.', 'success')
        go('employee')

    accounts = db.many(
        'SELECT a.account_number, a.balance, a.type, u.username, u.first_name, u.last_name'
        ' FROM accounts a JOIN users u ON u.id = a.user_id ORDER BY a.balance DESC'
    )
    terminable = terminable_employees(me) if me['tier'] > 1 else []
    return render_staff('employee.html', me, f"Welcome to work, {me['first_name']} {me['last_name']}!", 'employee',
                        accounts=accounts, terminable=terminable)


@bp.route('/employee-deposits', methods=['GET', 'POST'])
def employee_deposits():
    me = require_employee()
    if request.method == 'POST':
        decision = form('decision')
        if decision not in ('approve', 'deny'):
            go('employee-deposits')
        if not banking.review_deposit(me['id'], form('transaction'), decision == 'approve'):
            back_with('employee-deposits', 'That deposit was already reviewed.')
        back_with('employee-deposits', 'Deposit approved.' if decision == 'approve' else 'Deposit denied.', 'success')

    deposits = db.many(
        "SELECT t.id, t.amount, t.account_number, t.created_at, u.first_name, u.last_name"
        " FROM transactions t"
        " JOIN check_deposits d ON d.transaction_id = t.id"
        " JOIN accounts a ON a.account_number = t.account_number"
        " JOIN users u ON u.id = a.user_id"
        " WHERE t.type = 'Deposit' AND t.status = 'Pending'"
        " ORDER BY t.created_at"
    )
    return render_staff('employee-deposits.html', me, 'Pending Deposits', 'employee-deposits', deposits=deposits)


@bp.route('/check-image')
def check_image():
    """Serves uploaded check images to employees. Uploads live outside the web root so they
    can't be fetched directly by guessing a URL."""
    require_employee()
    column = 'back_image' if form('side') == 'back' else 'front_image'
    name = db.value(f'SELECT {column} FROM check_deposits WHERE transaction_id = %s', (form('id'),))
    if not name:
        abort(404)
    response = send_from_directory(banking.DEPOSIT_UPLOADS, name)  # refuses paths outside the folder; 404 if missing
    response.headers['Cache-Control'] = 'private, max-age=3600'  # never stored by shared caches
    return response


@bp.route('/employee-inquiries', methods=['GET', 'POST'])
def employee_inquiries():
    me = require_employee()
    if request.method == 'POST':
        status = {'resolve': 'Resolved', 'reopen': 'Open'}.get(form('action'))
        if status:
            db.execute('UPDATE support_tickets SET status = %s WHERE id = %s', (status, form('ticket')))
        go('employee-inquiries')

    tickets = {'Open': [], 'Resolved': []}
    for ticket in db.many('SELECT * FROM support_tickets ORDER BY created_at'):
        tickets[ticket['status']].append(ticket)
    return render_staff('employee-inquiries.html', me, 'Inquiries', 'employee-inquiries', tickets=tickets)
