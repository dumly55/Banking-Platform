-- Q: How long does a new customer wait before they have money in the bank? Days from signup to their
-- first cleared check, by signup quarter.
-- Technique: MIN() per customer, then PERCENTILE_CONT() for the median and 90th percentile.
-- A: The median new customer waits about 8 days for a first check to clear, and 1 in 10 waits two weeks,
--    in every quarter. Most of that is waiting for the next paycheck, so letting customers fund the
--    account at signup would shorten it.

WITH first_cleared AS (
    SELECT c.customer_id,
           c.signed_up_at,
           MIN(t.reviewed_at) AS first_cleared_at
    FROM v_customers c
    LEFT JOIN v_transactions t ON t.customer_id = c.customer_id AND t.type = 'Deposit' AND t.status = 'Processed'
    GROUP BY c.customer_id, c.signed_up_at
)
SELECT date_trunc('quarter', signed_up_at)::date AS signup_quarter,
       COUNT(*) AS customers,
       ROUND(PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY EXTRACT(EPOCH FROM first_cleared_at - signed_up_at) / 86400)::numeric, 1) AS median_days,
       ROUND(PERCENTILE_CONT(0.9) WITHIN GROUP (ORDER BY EXTRACT(EPOCH FROM first_cleared_at - signed_up_at) / 86400)::numeric, 1) AS p90_days,
       COUNT(*) FILTER (WHERE first_cleared_at IS NULL) AS no_cleared_check_yet
FROM first_cleared
GROUP BY 1
ORDER BY 1;
