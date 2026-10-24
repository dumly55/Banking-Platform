"""Checks that the simulated bank keeps its books straight. Run: python -m unittest

Builds a small bank in its own PostgreSQL database, bank_test, so the real "bank" database is untouched.
"""
import tempfile
import unittest
from pathlib import Path

import build


class SimulationTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.tmp = tempfile.TemporaryDirectory()
        cls.db = build.build('bank_test', customers=200)

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
            self.assertEqual(before, running.get(number, 0))
            running[number] = running.get(number, 0) + amount
            self.assertGreaterEqual(running[number], 0, 'account overdrawn')
        for number, balance in self.db.execute('SELECT account_number, balance FROM accounts'):
            self.assertEqual(running.get(number, 0), balance)

    def test_transfers_come_in_matching_pairs(self):
        sent = self.scalar("SELECT COUNT(*) FROM transactions WHERE type = 'Transfer Out'")
        self.assertGreater(sent, 0)
        self.assertEqual(self.scalar("SELECT COUNT(*) FROM transactions WHERE type = 'Transfer In'"), sent)
        self.assertEqual(self.scalar(
            """SELECT COUNT(*) FROM transactions o LEFT JOIN transactions i ON i.id = o.related_transaction
               WHERE o.type = 'Transfer Out'
                 AND (i.type IS DISTINCT FROM 'Transfer In' OR i.related_transaction IS DISTINCT FROM o.id
                      OR i.amount != -o.amount)"""), 0)

    def test_deposits_are_reviewed_on_weekdays_after_submission(self):
        statuses = {r[0] for r in self.db.execute("SELECT DISTINCT status FROM transactions WHERE type = 'Deposit'")}
        self.assertEqual(statuses, {'Pending', 'Processed', 'Denied'})
        self.assertEqual(self.scalar(
            """SELECT COUNT(*) FROM transactions t JOIN check_deposits d ON d.transaction_id = t.id
               WHERE CASE t.status WHEN 'Pending' THEN d.reviewed_by IS NOT NULL
                     ELSE d.reviewed_at <= t.created_at OR EXTRACT(ISODOW FROM d.reviewed_at) >= 6 END"""), 0)

    def test_interest_matches_the_rate(self):
        self.assertGreater(self.scalar("SELECT COUNT(*) FROM transactions WHERE type = 'Interest'"), 0)
        self.assertEqual(self.scalar(
            """SELECT COUNT(*) FROM transactions t JOIN accounts a ON a.account_number = t.account_number
               WHERE t.type = 'Interest' AND ABS(t.amount - t.balance_before * a.interest_rate) > 0.005"""), 0)

    def test_views_agree_with_the_tables(self):
        for view, table in [('v_transactions', 'transactions'), ('v_customers', 'customers')]:
            self.assertEqual(self.scalar(f'SELECT COUNT(*) FROM {view}'), self.scalar(f'SELECT COUNT(*) FROM {table}'))
        # Every account's last month-end balance is exactly its current balance.
        self.assertEqual(self.scalar(
            """SELECT COUNT(*) FROM accounts a LEFT JOIN v_monthly_balances m
                 ON m.account_number = a.account_number AND m.month = (SELECT MAX(month) FROM v_monthly_balances)
               WHERE m.end_balance IS DISTINCT FROM a.balance"""), 0)
        self.assertEqual(self.scalar('SELECT COUNT(*) FROM v_cohort_retention WHERE retention NOT BETWEEN 0 AND 1'), 0)

    def test_export_writes_every_view_row(self):
        folder = Path(self.tmp.name) / 'tableau'
        build.export(self.db, folder)
        for view in build.VIEWS:
            with open(folder / f'{view[2:]}.csv', encoding='utf-8') as f:
                self.assertEqual(sum(1 for _ in f) - 1, self.scalar(f'SELECT COUNT(*) FROM {view}'))


if __name__ == '__main__':
    unittest.main()
