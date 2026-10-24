-- Q: Do some reviewers deny far more checks than the rest of the team?
-- Compares each reviewer's denial rate with the team's. A z-score beyond +/-2 is unlikely to be chance
-- (reviews are assigned at random, so every reviewer sees a similar mix of checks).
-- Technique: grouped counts with FILTER, a one-row CTE for the team rate, and a proportion z-test in SQL.
-- A: Yes. Teller 7 denies 5.1% of checks and teller 3 denies 1.6%, against a team rate of 3.2%
--    (z = +8.0 and -8.9, far beyond chance). The same check can get a different answer depending on who
--    reviews it, which points to unclear review guidelines.

WITH reviews AS (
    SELECT reviewed_by, reviewer_tier, COUNT(*) AS reviewed, COUNT(*) FILTER (WHERE status = 'Denied') AS denied
    FROM v_transactions
    WHERE type = 'Deposit' AND status <> 'Pending'
    GROUP BY 1, 2
),
team AS (
    SELECT SUM(denied)::numeric / SUM(reviewed) AS rate FROM reviews
)
SELECT r.reviewed_by AS employee_id,
       r.reviewer_tier,
       r.reviewed,
       r.denied,
       ROUND(100.0 * r.denied / r.reviewed, 2) AS denial_pct,
       ROUND(100 * team.rate, 2) AS team_pct,
       ROUND((r.denied::numeric / r.reviewed - team.rate) / sqrt(team.rate * (1 - team.rate) / r.reviewed), 1) AS z_score
FROM reviews r
CROSS JOIN team
ORDER BY z_score DESC;
