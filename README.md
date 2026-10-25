# Bank of the Future

An online banking web app built with Python (Flask) and MySQL. Customers can open checking and savings accounts, move money, use a virtual ATM and deposit checks by photo. Employees get a back office for reviewing deposits, answering support tickets, and hiring or terminating staff.

> This is an educational project, not a real bank. Don't enter real personal information.

The app was first written in PHP and later ported to Python, keeping the same pages, rules and database. The PHP version is in the git history.

![Customer dashboard](docs/screenshots/dashboard.webp)

## Screenshots

| | |
|---|---|
| ![Home page](docs/screenshots/home.webp) <br> **Public site** | ![Login](docs/screenshots/login.webp) <br> **Customer login** |
| ![Multi-factor authentication](docs/screenshots/mfa.webp) <br> **Emailed one-time code** (shown on screen in dev mode) | ![Transfer](docs/screenshots/transfer.webp) <br> **Transfers** |
| ![Account history](docs/screenshots/account.webp) <br> **Account history** | ![ATM](docs/screenshots/atm.webp) <br> **Virtual ATM** |
| ![Check deposit](docs/screenshots/deposit.webp) <br> **Mobile check deposit** | ![Employee login](docs/screenshots/employee-login.webp) <br> **Employee login with on-screen keyboard** |
| ![Employee dashboard](docs/screenshots/employee.webp) <br> **Employee dashboard** | ![Pending deposits](docs/screenshots/employee-deposits.webp) <br> **Deposit review** |
| ![Support inquiries](docs/screenshots/employee-inquiries.webp) <br> **Support tickets** | |

## Features

**Customers**
- Registration, then login with a password plus a 6-digit code sent by email
- Checking (0.05% APY) and savings (0.57% APY) accounts. Interest compounds yearly and is posted as a transaction
- Instant transfers between your own accounts. Transfers to another customer need a second emailed code
- Virtual ATM: pick a debit card, enter your 4-digit PIN and withdraw
- Mobile check deposit: upload the front and back of a check. The deposit stays pending until an employee approves it
- Full transaction history per account
- Password reset and username recovery by email
- Logout after 10 minutes of inactivity, with a warning at 5 minutes

**Employees**
- Login with employee ID, password and an emailed code. An optional on-screen keyboard is available
- View every account, approve or deny check deposits, and resolve or reopen support tickets
- Tiered roles: tellers (1), managers (2) and admins (3). Managers and admins can hire into lower tiers, and new hires register with the employee ID from their offer email
- Managers can terminate their own hires and admins can terminate anyone below them. Access is revoked on the terminated employee's next request

## Data analysis

The app's database is also the subject of an analysis, in [analysis/](analysis/).

**The question:** the bank is growing fast, from 35 to about 120 new customers a month. Is it keeping them, and where does its back office let customers down?

**The data:** `flask --app bank setup-db --fresh --simulate` fills the database with two years of activity (October 2024 to September 2026): about 2,000 customers, 2,750 accounts, 154,000 transactions and 37,000 check reviews. [bank/simulate.py](bank/simulate.py) generates it with the app's own rules: deposits wait for an employee's review on weekdays, overdrafts are refused, and interest compounds yearly.

### Findings

| Finding | Evidence | Recommendation |
|---|---|---|
| **Weekend checks wait days, not hours** | Checks sent on Saturday wait a median of 47 hours, against about 10 on weekdays. A Mann-Whitney U test gives p < 0.001. About 100 checks a week arrive on weekends. | Add a short Saturday review shift. |
| **Reviewers don't agree on what to deny** | Denial rates run from 1.4% to 4.6% by reviewer. Chi-square tests within each check size give p < 0.001, and every reviewer sees the same mix of checks. | Write down denial criteria, and have reviewers regularly judge the same sample of checks. |
| **Going quiet is the warning sign** | "Days since last activity" alone predicts who stops using the bank (ROC AUC 0.92). A logistic regression only reaches 0.95, and balance, savings and denied checks add nothing. 79 customers look at risk today, holding $1.4M. | Send a weekly report of customers who have gone quiet, largest balances first. |
| **A few customers hold most of the money** | The top 10% of customers hold 39% of deposits (Gini 0.54). Dormant customers still hold $2.5M, 10% of the total. | Put the largest balances first in any retention work. |
| **New customers wait a week for money to arrive** | The median new customer waits 7–9 days for a first check to clear, and 1 in 10 waits two weeks. | Let customers fund the account when they sign up. |

About 79% of customers are still active a year after signing up. Customers fade out gradually rather than leaving right after joining.

### How it's done

- **SQL (MySQL 8):** [analysis/queries/](analysis/queries/) holds 9 queries, each answering one business question, with its answer in the header. They run against the app's normalized tables using joins, CTEs, window functions (`LAG`, `NTILE`, running `SUM() OVER`), cohort analysis, medians and percentiles built from `ROW_NUMBER()` (MySQL has no `PERCENTILE_CONT`), `WITH ROLLUP` totals, and a z-test in SQL.
- **Python:** [analysis/analysis.ipynb](analysis/analysis.ipynb) uses pandas and matplotlib to explore (Lorenz curve and Gini, review-time spread, a cohort retention heatmap), scipy for hypothesis tests (Mann-Whitney U, chi-square, Wilson confidence intervals), and scikit-learn for a dormancy model. The model uses features built from data before the snapshot date only, is compared against a simple baseline, and is explained with permutation importance.
- **Data quality:** the cohort analysis exposed demo customers whose activity predated their signup, which was fixed in the seed data.

**Limitations:** the data is simulated, so these patterns come from rules built into the simulation, such as stricter reviewers and business-hours reviews. The point is the method: the same queries, tests and model would surface them in real data.

### Run it yourself

```bash
flask --app bank setup-db --fresh --simulate     # about 15 seconds; also loads the demo accounts
pip install -r analysis/requirements.txt          # pandas, matplotlib, scipy, scikit-learn, jupyter
```

Run any file in `analysis/queries/` in MySQL Workbench or VS Code's SQLTools. Then open `analysis/analysis.ipynb` in VS Code or Jupyter. Simulated customers and staff log in with `Password123` (customer PIN `1234`), like the demo accounts.

## Quick start

**Requirements:** Python 3.9+ (tested on 3.12) and MySQL 8 or MariaDB 10.5+.

```bash
git clone https://github.com/dumly55/Banking-Platform.git
cd Banking-Platform
python -m venv .venv
.venv\Scripts\activate                 # macOS/Linux: source .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env                   # Windows: copy .env.example .env. Then set DB_USER / DB_PASS
flask --app bank setup-db --seed       # creates the database, tables and demo data
flask --app bank run --port 8000       # open http://localhost:8000
```

Add `--debug` to the last command while you work on the code, so the server reloads when files change.

`setup-db` won't overwrite a database that already has tables. To wipe and recreate everything, run `flask --app bank setup-db --fresh`.

### Demo accounts

Every demo account uses the password **`Password123`**.

| Role | Login | Notes |
|---|---|---|
| Customer | `demo` | PIN `1234`. Has a checking and a savings account |
| Customer | `jdoe` | PIN `4321`. Use checking account `4100987654320003` to test transfers to another person |
| Admin (tier 3) | employee ID `100000001` | Can hire and terminate anyone below tier 3 |
| Manager (tier 2) | employee ID `100000002` | Can hire tellers and terminate their own hires |
| Teller (tier 1) | employee ID `100000003` | Reviews deposits and tickets |
| New hire | employee ID `100000004` + `newhire@bankofthefuture.test` | Not registered yet. Try it at *Employee Login → New Employee? Register* |

### Email and verification codes

If `SMTP_HOST` is empty (the default), emails are not sent. They are appended to `storage/logs/mail.log` instead, and the verification page shows the code so you can log in locally without a mail server.

To send real email, fill in the SMTP settings in `.env`. For Gmail, use `smtp.gmail.com`, port `587`, and an [App Password](https://support.google.com/accounts/answer/185833). Never commit `.env`. It's in `.gitignore`.

### Deploying

- Don't use `flask run` in production. Use a production server, for example `pip install waitress` then `waitress-serve --port 8000 --call bank:create_app`, or `gunicorn 'bank:create_app()'` on Linux, behind a web server that handles HTTPS.
- Set `APP_URL` to the site's `https://` address. Session cookies are then marked `Secure`, and emailed links point to the right place.
- Leave out `APP_ENV` or set it to `production`. Without a `.env` the app runs in production mode, which never shows error details or login codes on screen.
- Don't load demo data. `setup-db` refuses `--seed` and `--fresh` in production, because the demo passwords are public.
- `setup-db` generates `APP_KEY` in `.env`. Keep it secret and back it up. Changing it stops the app from detecting duplicate SSNs.
- `storage/` must be writable: it holds sessions, uploaded check images and the mail log.

## Project structure

```
bank/                  The Flask app
  __init__.py          App factory: sessions, CSRF check, security headers, template filters
  config.py            Reads settings from .env / environment variables
  db.py                MySQL connection and query helpers
  auth.py              Passwords, sessions, login checks, inactivity timeout
  mfa.py               Emailed one-time codes
  banking.py           Accounts, transfers, withdrawals, deposits, interest
  validation.py        Form validation shared by customer and employee sign-up
  mail.py              SMTP sending (or a log file in development)
  helpers.py           Money formatting, form input, redirects, CSRF tokens
  public.py            Marketing pages and the contact form
  access.py            Login, verification codes, registration, password reset, logout
  customer.py          Dashboard, accounts, transfers, ATM, check deposits
  staff.py             Employee login, hiring, deposit review, support tickets
  setup.py             The `flask setup-db` command and demo data
  simulate.py          Two years of simulated activity (`setup-db --simulate`) for analysis
  templates/           One Jinja template per page, plus shared layout and nav
  static/              CSS, JavaScript and images, served at /assets
analysis/
  queries/             Business-question SQL, each with its answer in the header
  analysis.ipynb       Python analysis: exploration, hypothesis tests, dormancy model
  requirements.txt     Extra libraries for the notebook
database/schema.sql    Table definitions
storage/               Sessions, uploaded check images and the mail log (git-ignored)
tests/smoke.sh         End-to-end test suite
tests/test_simulate.py Checks that the simulated history follows the bank's rules
```

## Security

- All SQL uses parameterized queries
- Passwords and PINs are hashed with bcrypt. Hashes created by the earlier PHP version still work
- Sessions are stored on the server. The browser only gets a random session ID, which changes at login. Cookies are `HttpOnly`, `SameSite=Lax`, and `Secure` when `APP_URL` uses HTTPS
- Every form has a CSRF token
- Customers can only see and move money from their own accounts. All balance changes happen inside database transactions with row locks, and the database rejects negative balances
- Employee permissions (tier, manager relationship, employment status) are checked on the server for every request
- Check images are stored outside the web folder, validated by file content rather than extension, given random names, and served only to employees, never cached by shared caches
- Verification codes expire after 10 minutes and allow 5 attempts. Five wrong ATM PINs end the session
- SSNs are never stored. Only a keyed hash (HMAC with `APP_KEY`) is kept, which is enough to block duplicate sign-ups
- Login, password reset and username recovery respond the same whether or not an account exists, so they can't be used to find out who banks here
- Statements show only the last 4 digits of the other account in a transfer
- Secrets live in `.env` (git-ignored)

Known limits for a demo project: there is no rate limiting on login, and names, addresses and license numbers are stored as plain text.

## Testing

With the app running on seeded data and `SMTP_HOST` empty:

```bash
flask --app bank setup-db --fresh
flask --app bank run --port 8000       # in a second terminal
bash tests/smoke.sh                    # or BASE_URL=http://localhost:5000 bash tests/smoke.sh
```

The suite needs `bash` and `curl`, which Git Bash on Windows includes. It makes 72 checks covering login and MFA, transfers, ATM withdrawals, deposits and their review, registration, hiring and termination, support tickets and password reset. It also checks that the access-control rules hold, for example that you can't transfer out of someone else's account. The suite changes demo data (for example, `jdoe`'s password becomes `NewPass456`), so run `flask --app bank setup-db --fresh` afterwards for a clean demo.

The simulated history has its own checks, which don't need a database:

```bash
python -m unittest tests/test_simulate.py
```

They replay every account's ledger, and check that transfers pair up, that reviews happen during business hours, and that interest matches each account's rate.

## Tech stack

Python 3 with Flask, Jinja2 templates, Flask-Session (server-side sessions), PyMySQL, bcrypt and MySQL/MariaDB, plus plain HTML/CSS/JavaScript with no front-end build step.
