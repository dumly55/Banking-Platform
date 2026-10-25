-- Q: How much money sits with customers who stopped using the bank, and how long have they been
--    gone? These are the customers a win-back campaign would target.
-- Technique: "dormant" = no deposit, withdrawal or transfer out in the 90 days before the latest data
--    (interest and incoming transfers don't count as using the bank). CASE buckets, then
--    GROUP BY ... WITH ROLLUP for a total row, labeled with GROUPING().
-- A: 251 dormant customers still hold $2.5M, 10% of all money. The 174 who left less than a year ago
--    hold $2.1M of it and are the likeliest to come back.

WITH as_of AS (
    SELECT MAX(created_at) AS t FROM transactions
),
last_active AS (
    SELECT u.id, COALESCE(MAX(t.created_at), u.created_at) AS last_active
    FROM users u
    LEFT JOIN accounts a ON a.user_id = u.id
    LEFT JOIN transactions t ON t.account_number = a.account_number AND t.type IN ('Deposit', 'Withdrawal', 'Transfer Out')
    GROUP BY u.id, u.created_at
),
holdings AS (
    SELECT user_id, SUM(balance) AS balance FROM accounts GROUP BY user_id
),
dormant AS (
    SELECT h.balance,
           l.last_active,
           CASE WHEN l.last_active >= as_of.t - INTERVAL 180 DAY THEN '3-6 months'
                WHEN l.last_active >= as_of.t - INTERVAL 365 DAY THEN '6-12 months'
                ELSE 'Over a year' END AS inactive_for
    FROM last_active l
    JOIN holdings h ON h.user_id = l.id
    CROSS JOIN as_of
    WHERE l.last_active < as_of.t - INTERVAL 90 DAY
)
SELECT IF(GROUPING(inactive_for), 'All dormant', inactive_for) AS inactive_for,
       COUNT(*) AS customers,
       SUM(balance) AS money_held,
       ROUND(AVG(balance), 2) AS avg_balance,
       ROUND(100 * SUM(balance) / (SELECT SUM(balance) FROM accounts), 1) AS share_of_all_money_pct
FROM dormant
GROUP BY inactive_for WITH ROLLUP
ORDER BY GROUPING(inactive_for), MIN(last_active) DESC;
