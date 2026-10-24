# Bank of the Future: Banking Analytics

A data analysis project built on the data model of an online bank. Python simulates two years of realistic banking activity, SQL turns it into analysis-ready views, and Tableau shows growth, customer retention and back-office performance.

> The data is synthetic. It comes from a seeded simulation, so every run produces the same numbers. The project started as a PHP/MySQL banking web app, which is still on the `main` branch and in the git history.

```
build.py (Python simulation)  ->  data/bank.db (SQLite tables + SQL views)  ->  data/tableau/*.csv  ->  Tableau
```

## Quick start

Requires Python 3.9+ (tested on 3.12). It uses only the standard library, so there's nothing to install.

```bash
python build.py          # about 5 seconds: data/bank.db and data/tableau/*.csv
python -m unittest       # checks the books balance
```

Then open the CSVs in Tableau Public or Tableau Desktop (see [Tableau dashboards](#tableau-dashboards)), or query `data/bank.db` with any SQLite client.

## What's simulated

2,000 customers sign up between October 2024 and September 2026, at a rate that triples over the two years. They live in states roughly in proportion to the US population. Each customer gets a checking account, and 45% open savings within six months. The simulation follows the original web app's rules:

| Activity | How it works |
|---|---|
| Mobile check deposits | A paycheck every two weeks (median $1,500). The deposit stays **Pending** until an employee reviews it. Reviews happen on weekdays from 9:00 to 17:00, so checks sent on weekends wait longer. Checks over $2,500 are denied more often, and some reviewers deny more than others. |
| ATM withdrawals | Cash in multiples of $20, up to $1,000 per visit. Withdrawals that would overdraw the account are refused and not recorded. |
| Transfers | Monthly savings transfers between a customer's own accounts, plus payments to other customers. Each transfer is two linked rows: **Transfer Out** and **Transfer In**. |
| Interest | Compounds yearly on each account's opening anniversary: 0.05% for checking, 0.57% for savings. |
| Customer churn | Customers stop using the bank at random, on average after about four years. Their accounts then go dormant. |

To change the size or time range, edit the constants at the top of [build.py](build.py) (`CUSTOMERS`, `START`, `END`, `EMPLOYEES`).

## Data model

Defined in [sql/schema.sql](sql/schema.sql):

| Table | Rows (default run) | Contents |
|---|---|---|
| `customers` | 2,000 | State and signup time |
| `employees` | 7 | Tier (1 teller, 2 manager, 3 admin), manager and hire date |
| `accounts` | ~2,800 | Checking or savings, current balance and interest rate |
| `transactions` | ~150,000 | Deposits, withdrawals, transfers and interest. `amount` is negative when money leaves |
| `check_deposits` | ~37,000 | Who reviewed each check deposit, and when |

## Analysis views

Defined in [sql/views.sql](sql/views.sql). Each view is exported to `data/tableau/<name>.csv`:

| View | Grain | Questions it answers |
|---|---|---|
| `v_transactions` | One row per transaction | What is the transaction mix by month? How long do check reviews take, and who denies the most? How many transfers go to other customers versus a customer's own accounts? |
| `v_monthly_balances` | Account × month | How much money does the bank hold over time, split into checking and savings? Uses a recursive calendar CTE and a running-sum window function. |
| `v_customers` | One row per customer | Where are customers located? How are balances distributed? Who is **Active** (moved money in the last 90 days) and who is **Dormant**? |
| `v_cohort_retention` | Signup month × months since signup | What share of each signup cohort is still active N months later? |

"Active" means the customer moved money themselves: a deposit, a withdrawal or a transfer out. Interest and incoming transfers don't count.

## Tableau dashboards

### Connect the data

1. Run `python build.py`.
2. In Tableau, choose **Connect → Text file** and open `data/tableau/customers.csv`.
3. Drag `transactions.csv` and `monthly_balances.csv` onto the canvas next to it. Tableau will ask how to relate them. Relate both on `customer_id = customer_id`.
4. Add `cohort_retention.csv` as a **separate** data source. It's already aggregated, so don't relate it to the others.
5. Check that `state` and `customer_state` have the **State/Province** geographic role. Tableau usually assigns it automatically.

### 1. Growth

| Sheet | Build |
|---|---|
| Money held | `monthly_balances`: `MONTH(month)` on Columns, `SUM(end_balance)` on Rows, `account_type` on Color. Use an area chart. |
| New customers | `customers`: `MONTH(cohort)` on Columns, `COUNTD(customer_id)` on Rows. Use bars. |
| Customers by state | `customers`: `state` on Detail, `COUNTD(customer_id)` on Color. Use a filled map. |
| KPI tiles | Customers: `COUNTD(customer_id)`. Active rate: `AVG(IIF([status] = 'Active', 1, 0))`. Money held: `SUM(end_balance)` filtered to the latest `month`. |

### 2. Customer behavior

| Sheet | Build |
|---|---|
| Retention heatmap | `cohort_retention`: `months_since_signup` (discrete) on Columns, `MONTH(cohort)` on Rows, `AVG(retention)` on Color and Label. Use square marks. Retention falls with each month since signup, and the newest cohorts have only a few months of data. |
| Balance tiers | `customers`: `balance_tier` on Rows (sort it manually from Under $1K to $50K+), `COUNTD(customer_id)` on Columns, `status` on Color. |
| Transaction mix | `transactions`: filter `status` to Processed. `MONTH(created_at)` on Columns, `COUNTD(transaction_id)` on Rows, `type` on Color. |

### 3. Check deposit operations

Filter every sheet to `type` = Deposit.

| Sheet | Build |
|---|---|
| Review time by weekday | Exclude `status` = Pending. `WEEKDAY(created_at)` on Columns, `AVG(review_hours)` on Rows. Weekend checks wait for Monday. |
| Denial rate by reviewer | Create a calculated field `Denied = IIF([status] = 'Denied', 1, 0)`. Put `reviewed_by` (discrete) on Rows, `AVG(Denied)` on Columns and `reviewer_tier` on Color. |
| Review queue | `COUNTD(transaction_id)` filtered to `status` = Pending. These are checks still waiting at the end of the data. |

Tableau Public publishes workbooks to your public profile. That's fine here because the data is synthetic.

## Testing

`python -m unittest` builds a 200-customer bank in a temporary folder and checks:
- Replaying the ledger reproduces every `balance_before` and every final balance, and no account ever goes below zero.
- Every Transfer Out has exactly one matching Transfer In.
- Check deposits are reviewed after they're submitted and only on weekdays, and pending deposits have no reviewer.
- Interest payments match each account's rate.
- The views agree with the tables, and every view row is exported to its CSV.

## Project structure

```
build.py           Simulation, SQLite load and CSV export
test_build.py      Ledger and view checks
sql/schema.sql     Tables
sql/views.sql      Analysis views (the Tableau data sources)
data/              Generated output (git-ignored): bank.db and tableau/*.csv
```
