-- Q: Where does the money come from and where does it go? Totals by channel for each quarter.
-- Technique: conditional aggregation with FILTER. Transfers between customers cancel out bank-wide,
-- so they're shown as volume only; net_change is how much the bank's total holdings moved.
-- A: About 60 cents of every dollar deposited leaves as ATM cash (48 cents in the first quarter). The rest
--    stays, so holdings grow every quarter (+$6.5M in Q3 2026). Interest is small: it starts a year in,
--    when the first accounts reach their anniversary, and was $3,750 in Q3 2026.

SELECT date_trunc('quarter', COALESCE(reviewed_at, created_at))::date AS quarter,
       SUM(amount) FILTER (WHERE type = 'Deposit')                                         AS checks_cleared,
       SUM(amount) FILTER (WHERE type = 'Withdrawal')                                      AS atm_cash,
       SUM(amount) FILTER (WHERE type = 'Interest')                                        AS interest_paid,
       -SUM(amount) FILTER (WHERE type = 'Transfer Out' AND transfer_kind = 'Another customer') AS sent_between_customers,
       SUM(amount)                                                                         AS net_change,
       ROUND(100 * -SUM(amount) FILTER (WHERE type = 'Withdrawal')
                 / SUM(amount) FILTER (WHERE type = 'Deposit'), 1)                         AS cash_out_per_dollar_in_pct
FROM v_transactions
WHERE status = 'Processed'
GROUP BY 1
ORDER BY 1;
