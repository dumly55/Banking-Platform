-- Q: Do customers keep using the bank? For each monthly signup cohort, the share still moving money
--    N months after signing up.
-- Technique: cohort analysis. Each customer's active months, as months since signup (PERIOD_DIFF),
--    against the cohort's size (COUNT() OVER the signup month). Month 0 is partial, since customers
--    join throughout the month.
-- A: Almost everyone is active in their first full month (97-100%). A year after signing up, 70-88%
--    of each cohort is still active (about 79% on average), and the first cohort is at 54% after
--    20 months. Customers leave slowly and steadily rather than right after joining.

WITH active AS (
    SELECT DISTINCT
           u.id,
           PERIOD_DIFF(DATE_FORMAT(t.created_at, '%Y%m'), DATE_FORMAT(u.created_at, '%Y%m')) AS months_since_signup
    FROM transactions t
    JOIN accounts a ON a.account_number = t.account_number
    JOIN users u ON u.id = a.user_id
    WHERE t.type IN ('Deposit', 'Withdrawal', 'Transfer Out')
),
cohorts AS (
    SELECT id,
           DATE_FORMAT(created_at, '%Y-%m') AS cohort,
           COUNT(*) OVER (PARTITION BY DATE_FORMAT(created_at, '%Y-%m')) AS cohort_size
    FROM users
)
SELECT c.cohort,
       a.months_since_signup,
       c.cohort_size,
       COUNT(*) AS active_customers,
       ROUND(COUNT(*) / c.cohort_size, 3) AS retention
FROM active a
JOIN cohorts c ON c.id = a.id
GROUP BY c.cohort, a.months_since_signup, c.cohort_size
ORDER BY c.cohort, a.months_since_signup;
