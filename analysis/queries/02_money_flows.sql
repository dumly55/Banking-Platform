-- Q: Where does the money come from and where does it go? Totals by channel for each quarter.
-- Technique: conditional aggregation (SUM(CASE ...)), and a self-join through related_transaction
--    to tell transfers between customers from transfers between a customer's own accounts.
--    Transfers between customers cancel out bank-wide, so they're shown as volume only.
-- A: About 60 cents of every dollar deposited leaves as ATM cash (49 cents in the first quarter).
--    The rest stays, so holdings grow every quarter (+$6.6M in Q3 2026). Interest starts a year in,
--    when the first accounts reach their anniversary, and stays small: $3,832 in Q3 2026.

WITH posted AS (
    SELECT t.type,
           t.amount,
           COALESCE(d.reviewed_at, t.created_at) AS posted_at,
           a.user_id <> other.user_id AS to_another_customer
    FROM transactions t
    JOIN accounts a ON a.account_number = t.account_number
    LEFT JOIN check_deposits d ON d.transaction_id = t.id
    LEFT JOIN transactions r ON r.id = t.related_transaction
    LEFT JOIN accounts other ON other.account_number = r.account_number
    WHERE t.status = 'Processed'
)
SELECT CONCAT(YEAR(posted_at), ' Q', QUARTER(posted_at)) AS quarter,
       SUM(CASE WHEN type = 'Deposit' THEN amount END) AS checks_cleared,
       SUM(CASE WHEN type = 'Withdrawal' THEN amount END) AS atm_cash,
       SUM(CASE WHEN type = 'Interest' THEN amount END) AS interest_paid,
       -SUM(CASE WHEN type = 'Transfer Out' AND to_another_customer THEN amount END) AS sent_between_customers,
       SUM(amount) AS net_change,
       ROUND(100 * -SUM(CASE WHEN type = 'Withdrawal' THEN amount END)
                 / SUM(CASE WHEN type = 'Deposit' THEN amount END), 1) AS cash_out_per_dollar_in_pct
FROM posted
GROUP BY quarter
ORDER BY quarter;
