-- Q: How long do customers wait for a check to be reviewed, depending on the day they send it?
-- Technique: median and 90th percentile per weekday with ROW_NUMBER() and COUNT() OVER, plus the
--    share waiting over a day. Only reviewed checks count (pending ones have no wait yet).
-- A: Checks sent Monday to Thursday are reviewed in about 10 hours (median). Saturday checks wait
--    47 hours, and every one waits over a day. Friday's median looks fine, but 46% of Friday checks
--    wait over a day because the evening ones sit through the weekend. About 100 checks a week
--    arrive on weekends, so a short Saturday review shift would remove most of the delay.

WITH reviews AS (
    SELECT WEEKDAY(t.created_at) AS day_number,   -- 0 = Monday
           DATE_FORMAT(t.created_at, '%a') AS day_sent,
           TIMESTAMPDIFF(MINUTE, t.created_at, d.reviewed_at) / 60 AS hours
    FROM transactions t
    JOIN check_deposits d ON d.transaction_id = t.id
    WHERE d.reviewed_at IS NOT NULL
),
ranked AS (
    SELECT day_number,
           day_sent,
           hours,
           ROW_NUMBER() OVER (PARTITION BY day_number ORDER BY hours) AS rn,
           COUNT(*) OVER (PARTITION BY day_number) AS n
    FROM reviews
)
SELECT day_sent,
       COUNT(*) AS checks,
       ROUND(AVG(CASE WHEN rn IN (FLOOR((n + 1) / 2), CEIL((n + 1) / 2)) THEN hours END), 1) AS median_hours,
       ROUND(MIN(CASE WHEN rn >= CEIL(0.9 * n) THEN hours END), 1) AS p90_hours,
       ROUND(100 * AVG(hours > 24), 1) AS over_24h_pct
FROM ranked
GROUP BY day_number, day_sent
ORDER BY day_number;
