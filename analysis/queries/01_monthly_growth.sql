-- Q: How fast is the bank growing? Money held at the end of each month and new customers,
--    with month-over-month change.
-- Technique: deposits count from when an employee approves them (check_deposits.reviewed_at),
--    everything else from when it happened; a running SUM() OVER gives the month-end total,
--    and LAG() compares each month with the one before.
-- A: Money held grew from $35K at the end of October 2024 to $25.1M at the end of September 2026.
--    Monthly growth slowed from triple digits to about 10% as the base grew, while signups rose
--    from 35 to about 120 a month.

WITH posted AS (
    SELECT t.amount, COALESCE(d.reviewed_at, t.created_at) AS posted_at
    FROM transactions t
    LEFT JOIN check_deposits d ON d.transaction_id = t.id
    WHERE t.status = 'Processed'
),
monthly AS (
    SELECT DATE_FORMAT(posted_at, '%Y-%m') AS month, SUM(amount) AS net_flow
    FROM posted
    GROUP BY month
),
held AS (
    SELECT month, SUM(net_flow) OVER (ORDER BY month) AS money_held
    FROM monthly
),
signups AS (
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS new_customers
    FROM users
    GROUP BY month
)
SELECT h.month,
       h.money_held,
       ROUND(100 * (h.money_held / LAG(h.money_held) OVER (ORDER BY h.month) - 1), 1) AS money_growth_pct,
       s.new_customers,
       ROUND(100 * (s.new_customers / LAG(s.new_customers) OVER (ORDER BY h.month) - 1), 1) AS signup_growth_pct
FROM held h
LEFT JOIN signups s ON s.month = h.month
ORDER BY h.month;
