-- Q: How long do customers wait for a check to be reviewed, depending on the day they send it?
-- Technique: PERCENTILE_CONT() WITHIN GROUP for the median and 90th percentile, FILTER for the share over 24 hours.
-- A: Checks sent Monday to Thursday are reviewed in about 9 hours (median). Saturday checks wait 46 hours,
--    and every one waits over a day. Sunday checks wait 22 hours, and checks sent late on Friday push
--    Friday's 90th percentile to 68 hours. A weekend review shift would remove most of the delay.

SELECT EXTRACT(ISODOW FROM created_at)::int AS day_number,
       to_char(created_at, 'Dy') AS day_sent,
       COUNT(*) AS checks,
       ROUND(PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY review_hours)::numeric, 1) AS median_hours,
       ROUND(PERCENTILE_CONT(0.9) WITHIN GROUP (ORDER BY review_hours)::numeric, 1) AS p90_hours,
       ROUND(100.0 * COUNT(*) FILTER (WHERE review_hours > 24) / COUNT(*), 1) AS over_24h_pct
FROM v_transactions
WHERE type = 'Deposit' AND status <> 'Pending'
GROUP BY 1, 2
ORDER BY 1;
