-- Q: How concentrated are deposits? The share of all money held by each tenth of customers,
--    richest first.
-- Technique: NTILE() to split customers into deciles, then SUM() OVER () for each decile's share
--    of the total and a running share.
-- A: Highly concentrated. The top 10% of customers hold 39% of all money and the top 20% hold 57%,
--    while the bottom half holds 14%.

WITH customer_balances AS (
    SELECT user_id, SUM(balance) AS balance
    FROM accounts
    GROUP BY user_id
),
ranked AS (
    SELECT balance, NTILE(10) OVER (ORDER BY balance DESC) AS decile
    FROM customer_balances
)
SELECT decile,
       COUNT(*) AS customers,
       SUM(balance) AS money_held,
       ROUND(100 * SUM(balance) / SUM(SUM(balance)) OVER (), 1) AS share_pct,
       ROUND(100 * SUM(SUM(balance)) OVER (ORDER BY decile) / SUM(SUM(balance)) OVER (), 1) AS cumulative_share_pct
FROM ranked
GROUP BY decile
ORDER BY decile;
