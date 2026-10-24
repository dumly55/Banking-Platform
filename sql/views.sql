-- Analysis layer. Each view answers one set of business questions and becomes one Tableau data source.
-- "Active" means the customer moved money themselves: a deposit, withdrawal or transfer out
-- (interest and incoming transfers don't count).

-- One row per transaction, with the account, customer and check-review details to slice by.
CREATE VIEW v_transactions AS
SELECT t.id                 AS transaction_id,
       t.created_at,
       t.type,
       t.status,
       t.amount,
       a.account_number,
       a.type               AS account_type,
       a.customer_id,
       c.state              AS customer_state,
       CASE WHEN t.related_transaction IS NULL THEN NULL
            WHEN other.customer_id = a.customer_id THEN 'Own accounts'
            ELSE 'Another customer' END AS transfer_kind,
       d.reviewed_by,
       e.tier               AS reviewer_tier,
       d.reviewed_at,
       ROUND(EXTRACT(EPOCH FROM d.reviewed_at - t.created_at) / 3600, 1) AS review_hours
FROM transactions t
JOIN accounts a ON a.account_number = t.account_number
JOIN customers c ON c.id = a.customer_id
LEFT JOIN transactions r ON r.id = t.related_transaction
LEFT JOIN accounts other ON other.account_number = r.account_number
LEFT JOIN check_deposits d ON d.transaction_id = t.id
LEFT JOIN employees e ON e.id = d.reviewed_by;

-- End-of-month balance of every account: how much money the bank holds over time.
-- Money posts when it moves, except check deposits, which post when an employee approves them.
CREATE VIEW v_monthly_balances AS
WITH months AS (
    SELECT month::date
    FROM generate_series((SELECT date_trunc('month', MIN(created_at)) FROM accounts),
                         (SELECT date_trunc('month', MAX(created_at)) FROM transactions),
                         interval '1 month') AS month
),
flows AS (
    SELECT t.account_number,
           date_trunc('month', COALESCE(d.reviewed_at, t.created_at))::date AS month,
           SUM(t.amount) AS net_flow
    FROM transactions t
    LEFT JOIN check_deposits d ON d.transaction_id = t.id
    WHERE t.status = 'Processed'
    GROUP BY 1, 2
)
SELECT m.month,
       a.account_number,
       a.type AS account_type,
       a.customer_id,
       COALESCE(f.net_flow, 0) AS net_flow,
       SUM(COALESCE(f.net_flow, 0)) OVER (PARTITION BY a.account_number ORDER BY m.month) AS end_balance
FROM accounts a
JOIN months m ON m.month >= date_trunc('month', a.created_at)
LEFT JOIN flows f ON f.account_number = a.account_number AND f.month = m.month;

-- One row per customer: what they hold, and whether they're still active (moved money in the last 90 days).
CREATE VIEW v_customers AS
WITH holdings AS (
    SELECT customer_id, COUNT(*) AS accounts, bool_or(type = 'Savings') AS has_savings, SUM(balance) AS balance
    FROM accounts
    GROUP BY customer_id
),
activity AS (
    SELECT a.customer_id, COUNT(*) AS transaction_count, MAX(t.created_at) AS last_active_at
    FROM transactions t
    JOIN accounts a ON a.account_number = t.account_number
    WHERE t.type IN ('Deposit', 'Withdrawal', 'Transfer Out')
    GROUP BY a.customer_id
)
SELECT c.id                                   AS customer_id,
       c.state,
       c.created_at                           AS signed_up_at,
       date_trunc('month', c.created_at)::date AS cohort,
       h.accounts,
       h.has_savings,
       h.balance,
       CASE WHEN h.balance < 1000 THEN 'Under $1K'
            WHEN h.balance < 10000 THEN '$1K-$10K'
            WHEN h.balance < 50000 THEN '$10K-$50K'
            ELSE '$50K+' END                  AS balance_tier,
       COALESCE(act.transaction_count, 0)     AS transaction_count,
       act.last_active_at,
       -- someone who just signed up and hasn't moved money yet still counts as active
       CASE WHEN COALESCE(act.last_active_at, c.created_at) >= (SELECT MAX(created_at) FROM transactions) - interval '90 days'
            THEN 'Active' ELSE 'Dormant' END  AS status
FROM customers c
JOIN holdings h ON h.customer_id = c.id
LEFT JOIN activity act ON act.customer_id = c.id;

-- Share of each monthly signup cohort that was active N months after signing up (a retention heatmap).
CREATE VIEW v_cohort_retention AS
WITH active AS (
    SELECT DISTINCT a.customer_id, date_trunc('month', t.created_at)::date AS month
    FROM transactions t
    JOIN accounts a ON a.account_number = t.account_number
    WHERE t.type IN ('Deposit', 'Withdrawal', 'Transfer Out')
),
cohorts AS (
    SELECT id AS customer_id,
           date_trunc('month', created_at)::date AS cohort,
           COUNT(*) OVER (PARTITION BY date_trunc('month', created_at)) AS cohort_size
    FROM customers
)
SELECT c.cohort,
       (EXTRACT(YEAR FROM age(a.month, c.cohort)) * 12 + EXTRACT(MONTH FROM age(a.month, c.cohort)))::int AS months_since_signup,
       c.cohort_size,
       COUNT(*) AS active_customers,
       ROUND(COUNT(*)::numeric / c.cohort_size, 3) AS retention
FROM cohorts c
JOIN active a ON a.customer_id = c.customer_id
GROUP BY 1, 2, 3;
