-- Q: How fast is the bank growing? Money held and new customers each month, with month-over-month change.
-- Technique: monthly aggregates joined, then LAG() to compare each month with the one before.
-- A: Money held grew from $45K in October 2024 to $25.4M in September 2026. Monthly growth slowed from
--    triple digits to about 10% as the base grew, while signups rose from 35 to about 120 a month.

WITH money AS (
    SELECT month, SUM(end_balance) AS money_held
    FROM v_monthly_balances
    GROUP BY month
),
signups AS (
    SELECT cohort AS month, COUNT(*) AS new_customers
    FROM v_customers
    GROUP BY cohort
)
SELECT m.month,
       m.money_held,
       ROUND(100 * (m.money_held / NULLIF(LAG(m.money_held) OVER (ORDER BY m.month), 0) - 1), 1) AS money_growth_pct,
       s.new_customers,
       ROUND(100 * (s.new_customers::numeric / LAG(s.new_customers) OVER (ORDER BY m.month) - 1), 1) AS signup_growth_pct
FROM money m
JOIN signups s ON s.month = m.month
ORDER BY m.month;
