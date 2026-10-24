"""Checks that the simulated bank keeps its books straight. Run: python -m unittest"""
import tempfile
import unittest
from pathlib import Path

import build


class SimulationTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.tmp = tempfile.TemporaryDirectory()
        cls.db = build.build(Path(cls.tmp.name) / 'bank.db', customers=200)

    @classmethod
    def tearDownClass(cls):
        cls.db.close()
        cls.tmp.cleanup()

    def scalar(self, sql):
        return self.db.execute(sql).fetchone()[0]

    def test_ledger_replays_to_every_balance(self):
        running = {}
        for number, amount, before in self.db.execute(
            """SELECT t.account_number, t.amount, t.balance_before FROM transactions t
               LEFT JOIN check_deposits d ON d.transaction_id = t.id
               WHERE t.status = 'Processed' ORDER BY COALESCE(d.reviewed_at, t.created_at), t.id"""
        ):
            self.assertAlmostEqual(before, running.get(number, 0), places=2)
            running[number] = running.get(number, 0) + amount
            self.assertGreater(running[number], -0.005, 'account overdrawn')
        for number, balance in self.db.execute('SELECT account_number, balance FROM accounts'):
            self.assertAlmostEqual(running.get(number, 0), balance, places=2)

    def test_transfers_come_in_matching_pairs(self):
        sent = self.scalar("SELECT COUNT(*) FROM transactions WHERE type = 'Transfer Out'")
        self.assertGreater(sent, 0)
        self.assertEqual(self.scalar("SELECT COUNT(*) FROM transactions WHERE type = 'Transfer In'"), sent)
        self.assertEqual(self.scalar(
            """SELECT COUNT(*) FROM transactions o LEFT JOIN transactions i ON i.id = o.related_transaction
               WHERE o.type = 'Transfer Out'
                 AND (i.type IS NOT 'Transfer In' OR i.related_transaction IS NOT o.id OR i.amount != -o.amount)"""), 0)

    def test_deposits_are_reviewed_on_weekdays_after_submission(self):
        statuses = {r[0] for r in self.db.execute("SELECT DISTINCT status FROM transactions WHERE type = 'Deposit'")}
        self.assertEqual(statuses, {'Pending', 'Processed', 'Denied'})
        self.assertEqual(self.scalar(
            """SELECT COUNT(*) FROM transactions t JOIN check_deposits d ON d.transaction_id = t.id
               WHERE CASE t.status WHEN 'Pending' THEN d.reviewed_by IS NOT NULL
                     ELSE d.reviewed_at <= t.created_at OR strftime('%w', d.reviewed_at) IN ('0', '6') END"""), 0)

    def test_interest_matches_the_rate(self):
        self.assertGreater(self.scalar("SELECT COUNT(*) FROM transactions WHERE type = 'Interest'"), 0)
        self.assertEqual(self.scalar(
            """SELECT COUNT(*) FROM transactions t JOIN accounts a USING (account_number)
               WHERE t.type = 'Interest' AND ABS(t.amount - t.balance_before * a.interest_rate) > 0.005001"""), 0)


if __name__ == '__main__':
    unittest.main()
