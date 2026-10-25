"""Checks that the simulated history follows the bank's rules. No database needed.

    python -m unittest tests/test_simulate.py
"""
import unittest
from collections import defaultdict
from datetime import time

from bank.banking import ACCOUNT_TYPES
from bank.simulate import END, START, simulate


class SimulationTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.sim = simulate(customers=200)
        cls.reviews = {tx_id: (reviewer, reviewed_at) for tx_id, reviewer, reviewed_at in cls.sim['check_deposits']}

    def posted(self):
        """Processed transactions in the order money moved: deposits when approved, the rest when made."""
        rows = [t for t in self.sim['transactions'] if t[3] == 'Processed']
        return sorted(rows, key=lambda t: self.reviews[t[0]][1] if t[0] in self.reviews else t[8])

    def test_ledger_replays_to_every_balance(self):
        running = defaultdict(int)
        for id_, number, kind, status, cents, before, *_ in self.posted():
            self.assertEqual(before, running[number], f'balance_before of {id_}')
            running[number] += cents
            self.assertGreaterEqual(running[number], 0, f'{number} overdrawn by {id_}')
        for number, _, _, balance, *_ in self.sim['accounts']:
            self.assertEqual(balance, running[number])

    def test_transfers_come_in_matching_pairs(self):
        by_id = {t[0]: t for t in self.sim['transactions']}
        sent = [t for t in self.sim['transactions'] if t[2] == 'Transfer Out']
        self.assertTrue(sent)
        for t in sent:
            other = by_id[t[7]]
            self.assertEqual((other[2], other[4], other[7]), ('Transfer In', -t[4], t[0]))

    def test_deposits_are_reviewed_in_business_hours_after_submission(self):
        statuses = {t[3] for t in self.sim['transactions'] if t[2] == 'Deposit'}
        self.assertEqual(statuses, {'Pending', 'Processed', 'Denied'})
        submitted = {t[0]: t[8] for t in self.sim['transactions']}
        for tx_id, (reviewer, reviewed_at) in self.reviews.items():
            if reviewer is None:
                continue
            self.assertGreater(reviewed_at, submitted[tx_id])
            self.assertLess(reviewed_at.weekday(), 5)
            self.assertGreaterEqual(reviewed_at.time(), time(9))

    def test_interest_matches_the_rate(self):
        rates = {number: rate for number, _, kind, _, rate, *_ in self.sim['accounts']}
        paid = [t for t in self.sim['transactions'] if t[2] == 'Interest']
        self.assertTrue(paid)
        for _, number, _, _, cents, before, *_ in paid:
            self.assertLessEqual(abs(cents - before * rates[number]), 0.5)

    def test_ids_and_dates_fit_the_schema(self):
        ids = [t[0] for t in self.sim['transactions']]
        self.assertEqual(len(ids), len(set(ids)))
        self.assertTrue(all(len(i) == 12 and i.isdigit() for i in ids))
        self.assertTrue(all(len(a[0]) == 16 and a[0][:4] == ACCOUNT_TYPES[a[2]][0] for a in self.sim['accounts']))
        self.assertTrue(all(START <= t[8] < END for t in self.sim['transactions']))

    def test_same_seed_same_history(self):
        self.assertEqual(simulate(customers=50)['transactions'], simulate(customers=50)['transactions'])


if __name__ == '__main__':
    unittest.main()
