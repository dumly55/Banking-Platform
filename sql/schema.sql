-- Bank of the Future data model (SQLite).
-- The same core tables as the original web app (database/schema.sql), without the login and
-- identity columns (passwords, PINs, SSNs, addresses) that an analytics copy has no use for.
-- Times are stored as 'YYYY-MM-DD HH:MM:SS' text, which SQLite's date functions and Tableau both read.

CREATE TABLE customers (
    id          INTEGER PRIMARY KEY,
    state       TEXT NOT NULL,                -- two-letter US state code
    created_at  TEXT NOT NULL                 -- signup time
);

CREATE TABLE employees (
    id          INTEGER PRIMARY KEY,
    tier        INTEGER NOT NULL CHECK (tier BETWEEN 1 AND 3),   -- 1 = teller, 2 = manager, 3 = admin
    manager_id  INTEGER REFERENCES employees (id),
    created_at  TEXT NOT NULL                 -- hire date
);

CREATE TABLE accounts (
    account_number  TEXT PRIMARY KEY,         -- 4100... checking, 4200... savings
    customer_id     INTEGER NOT NULL REFERENCES customers (id),
    type            TEXT NOT NULL CHECK (type IN ('Checking', 'Savings')),
    balance         REAL NOT NULL CHECK (balance >= 0),
    interest_rate   REAL NOT NULL,            -- annual, compounded on each opening anniversary
    created_at      TEXT NOT NULL
);
CREATE INDEX ix_accounts_customer ON accounts (customer_id);

CREATE TABLE transactions (
    id                   INTEGER PRIMARY KEY,
    account_number       TEXT NOT NULL REFERENCES accounts (account_number),
    type                 TEXT NOT NULL CHECK (type IN ('Deposit', 'Withdrawal', 'Transfer Out', 'Transfer In', 'Interest')),
    status               TEXT NOT NULL CHECK (status IN ('Pending', 'Processed', 'Denied')),
    amount               REAL NOT NULL,       -- negative when money leaves the account
    balance_before       REAL,                -- NULL until a pending deposit is reviewed
    related_transaction  INTEGER REFERENCES transactions (id),   -- the other side of a transfer
    created_at           TEXT NOT NULL
);
CREATE INDEX ix_transactions_account ON transactions (account_number, created_at);

-- Mobile check deposits wait here for an employee to approve or deny them.
CREATE TABLE check_deposits (
    transaction_id  INTEGER PRIMARY KEY REFERENCES transactions (id),
    reviewed_by     INTEGER REFERENCES employees (id),
    reviewed_at     TEXT                      -- NULL while pending; money posts at this time
);
