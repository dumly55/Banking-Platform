-- Bank of the Future data model (PostgreSQL).
-- The same core tables as the original web app (database/schema.sql in the git history), without the
-- login and identity columns (passwords, PINs, SSNs, addresses) that an analytics copy has no use for.
-- Rebuilding drops everything, including the views in views.sql.

DROP TABLE IF EXISTS check_deposits, transactions, accounts, employees, customers CASCADE;

CREATE TABLE customers (
    id          INTEGER   PRIMARY KEY,
    state       CHAR(2)   NOT NULL,             -- two-letter US state code
    created_at  TIMESTAMP NOT NULL              -- signup time
);

CREATE TABLE employees (
    id          INTEGER   PRIMARY KEY,
    tier        SMALLINT  NOT NULL CHECK (tier BETWEEN 1 AND 3),   -- 1 = teller, 2 = manager, 3 = admin
    manager_id  INTEGER   REFERENCES employees (id),
    created_at  TIMESTAMP NOT NULL              -- hire date
);

CREATE TABLE accounts (
    account_number  CHAR(16)      PRIMARY KEY,  -- 4100... checking, 4200... savings
    customer_id     INTEGER       NOT NULL REFERENCES customers (id),
    type            TEXT          NOT NULL CHECK (type IN ('Checking', 'Savings')),
    balance         NUMERIC(15,2) NOT NULL CHECK (balance >= 0),
    interest_rate   NUMERIC(6,4)  NOT NULL,     -- annual, compounded on each opening anniversary
    created_at      TIMESTAMP     NOT NULL
);
CREATE INDEX ix_accounts_customer ON accounts (customer_id);

CREATE TABLE transactions (
    id                   INTEGER       PRIMARY KEY,
    account_number       CHAR(16)      NOT NULL REFERENCES accounts (account_number),
    type                 TEXT          NOT NULL CHECK (type IN ('Deposit', 'Withdrawal', 'Transfer Out', 'Transfer In', 'Interest')),
    status               TEXT          NOT NULL CHECK (status IN ('Pending', 'Processed', 'Denied')),
    amount               NUMERIC(15,2) NOT NULL,   -- negative when money leaves the account
    balance_before       NUMERIC(15,2),            -- NULL until a pending deposit is reviewed
    related_transaction  INTEGER       REFERENCES transactions (id),   -- the other side of a transfer
    created_at           TIMESTAMP     NOT NULL
);
CREATE INDEX ix_transactions_account ON transactions (account_number, created_at);

-- Mobile check deposits wait here for an employee to approve or deny them.
CREATE TABLE check_deposits (
    transaction_id  INTEGER   PRIMARY KEY REFERENCES transactions (id),
    reviewed_by     INTEGER   REFERENCES employees (id),
    reviewed_at     TIMESTAMP                   -- NULL while pending; money posts at this time
);
