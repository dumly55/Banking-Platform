-- Q: Are large checks riskier? Denial rate by check amount.
-- Technique: CASE buckets, FILTER for denied counts and amounts, ORDER BY an aggregate to keep the buckets in size order.
-- A: Checks of $2,500 or more are denied about 4 times as often (8-9%) as smaller ones (2%).
--    They're 16% of checks but two-thirds of the denied dollars.

SELECT CASE WHEN amount < 1000 THEN 'Under $1,000'
            WHEN amount < 2500 THEN '$1,000-$2,499'
            WHEN amount < 5000 THEN '$2,500-$4,999'
            ELSE '$5,000+' END AS check_size,
       COUNT(*) AS checks,
       COUNT(*) FILTER (WHERE status = 'Denied') AS denied,
       ROUND(100.0 * COUNT(*) FILTER (WHERE status = 'Denied') / COUNT(*), 2) AS denial_pct,
       SUM(amount) FILTER (WHERE status = 'Denied') AS denied_amount
FROM v_transactions
WHERE type = 'Deposit' AND status <> 'Pending'
GROUP BY 1
ORDER BY MIN(amount);
