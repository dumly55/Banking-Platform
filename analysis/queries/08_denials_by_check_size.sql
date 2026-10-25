-- Q: Are large checks riskier? Denial rate and denied dollars by check amount.
-- Technique: CASE buckets, MySQL's boolean sums (SUM(status = 'Denied')), and ordering buckets by
--    their smallest amount so they come out in size order.
-- A: Checks of $2,500 or more are denied about 4 times as often (8%) as smaller ones (2%).
--    They're 16% of checks but 64% of the denied dollars.

SELECT CASE WHEN t.amount < 1000 THEN 'Under $1,000'
            WHEN t.amount < 2500 THEN '$1,000-$2,499'
            WHEN t.amount < 5000 THEN '$2,500-$4,999'
            ELSE '$5,000+' END AS check_size,
       COUNT(*) AS checks,
       SUM(t.status = 'Denied') AS denied,
       ROUND(100 * AVG(t.status = 'Denied'), 2) AS denial_pct,
       SUM(CASE WHEN t.status = 'Denied' THEN t.amount ELSE 0 END) AS denied_amount
FROM transactions t
JOIN check_deposits d ON d.transaction_id = t.id
WHERE t.status <> 'Pending'
GROUP BY check_size
ORDER BY MIN(t.amount);
