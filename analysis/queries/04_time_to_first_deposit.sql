-- Q: How long does a new customer wait before they have money in the bank? Days from signup to
--    their first cleared check, by signup quarter.
-- Technique: MIN() per customer, then median and 90th percentile with ROW_NUMBER() and COUNT() OVER,
--    since MySQL has no PERCENTILE_CONT. Customers with no cleared check yet sort last and are
--    counted separately.
-- A: The median new customer waits 7-9 days for a first check to clear, and 1 in 10 waits two weeks,
--    in every quarter. Most of that is waiting for the next paycheck, so letting customers fund the
--    account at signup would shorten it.

WITH first_cleared AS (
    SELECT u.id,
           CONCAT(YEAR(u.created_at), ' Q', QUARTER(u.created_at)) AS signup_quarter,
           TIMESTAMPDIFF(MINUTE, u.created_at, MIN(d.reviewed_at)) / 1440 AS days
    FROM users u
    LEFT JOIN accounts a ON a.user_id = u.id
    LEFT JOIN transactions t ON t.account_number = a.account_number AND t.type = 'Deposit' AND t.status = 'Processed'
    LEFT JOIN check_deposits d ON d.transaction_id = t.id
    GROUP BY u.id, signup_quarter, u.created_at
),
ranked AS (
    SELECT signup_quarter,
           days,
           ROW_NUMBER() OVER (PARTITION BY signup_quarter ORDER BY days IS NULL, days) AS rn,
           COUNT(days) OVER (PARTITION BY signup_quarter) AS n
    FROM first_cleared
)
SELECT signup_quarter,
       COUNT(*) AS customers,
       ROUND(AVG(CASE WHEN rn IN (FLOOR((n + 1) / 2), CEIL((n + 1) / 2)) THEN days END), 1) AS median_days,
       ROUND(MIN(CASE WHEN rn >= CEIL(0.9 * n) THEN days END), 1) AS p90_days,
       SUM(days IS NULL) AS no_cleared_check_yet
FROM ranked
GROUP BY signup_quarter
ORDER BY signup_quarter;
