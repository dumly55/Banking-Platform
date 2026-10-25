-- Q: Do some reviewers deny far more checks than the rest of the team?
-- Technique: each reviewer's denial rate against the team's, with a z-score from a one-proportion
--    z-test computed in SQL. Beyond +/-2 is unlikely to be chance. Reviews are assigned at random,
--    so every reviewer sees a similar mix of checks (query 08 shows size matters, so that's important).
-- A: Yes. Sam Rivera denies 1.4% of checks while Owen Park and Elena Ruiz deny 4.3-4.6%, against a
--    team rate of 3.1% (z = -9.5 and +6.4, far beyond chance). The same check can get a different
--    answer depending on who reviews it, which points to unclear review guidelines.

WITH reviews AS (
    SELECT e.id,
           CONCAT(e.first_name, ' ', e.last_name) AS reviewer,
           CASE e.tier WHEN 1 THEN 'Teller' WHEN 2 THEN 'Manager' ELSE 'Admin' END AS role,
           COUNT(*) AS reviewed,
           SUM(t.status = 'Denied') AS denied
    FROM check_deposits d
    JOIN transactions t ON t.id = d.transaction_id
    JOIN employees e ON e.id = d.reviewed_by
    GROUP BY e.id, reviewer, role
),
team AS (
    SELECT SUM(denied) / SUM(reviewed) AS rate FROM reviews
)
SELECT r.reviewer,
       r.role,
       r.reviewed,
       r.denied,
       ROUND(100 * r.denied / r.reviewed, 2) AS denial_pct,
       ROUND(100 * team.rate, 2) AS team_pct,
       ROUND((r.denied / r.reviewed - team.rate) / SQRT(team.rate * (1 - team.rate) / r.reviewed), 1) AS z_score
FROM reviews r
CROSS JOIN team
ORDER BY z_score DESC;
