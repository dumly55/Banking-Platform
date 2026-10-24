-- Q: How much money sits with customers who stopped using the bank, and how long have they been gone?
-- These are the customers a win-back campaign would target.
-- Technique: CASE buckets on date differences, GROUP BY ROLLUP for a total row, GROUPING() to label it.
-- A: 261 dormant customers still hold $2.4M, 9.5% of all money. The 168 who left less than a year ago
--    hold $1.8M of it and are the likeliest to come back.

WITH dormant AS (
    SELECT balance,
           COALESCE(last_active_at, signed_up_at) AS last_seen,
           CASE WHEN COALESCE(last_active_at, signed_up_at) >= (SELECT MAX(created_at) FROM transactions) - interval '180 days' THEN '3-6 months'
                WHEN COALESCE(last_active_at, signed_up_at) >= (SELECT MAX(created_at) FROM transactions) - interval '365 days' THEN '6-12 months'
                ELSE 'Over a year' END AS inactive_for
    FROM v_customers
    WHERE status = 'Dormant'
)
SELECT CASE WHEN GROUPING(inactive_for) = 1 THEN 'All dormant' ELSE inactive_for END AS inactive_for,
       COUNT(*) AS customers,
       SUM(balance) AS money_held,
       ROUND(AVG(balance), 2) AS avg_balance,
       ROUND(100 * SUM(balance) / (SELECT SUM(balance) FROM v_customers), 1) AS share_of_all_money_pct
FROM dormant
GROUP BY ROLLUP (inactive_for)
ORDER BY GROUPING(inactive_for), MIN(last_seen) DESC;
