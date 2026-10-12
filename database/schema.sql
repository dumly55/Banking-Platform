-- Bank of the Future schema (MySQL 8 / MariaDB 10.5+).
-- Load with: php database/setup.php   (or import this file into an empty database)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS check_deposits, transactions, accounts, users, support_tickets, employees;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(30)  NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    pin_hash        VARCHAR(255) NOT NULL,
    first_name      VARCHAR(50)  NOT NULL,
    last_name       VARCHAR(50)  NOT NULL,
    email           VARCHAR(254) NOT NULL UNIQUE,
    phone           CHAR(10)     NOT NULL UNIQUE,
    ssn             CHAR(9)      NOT NULL UNIQUE,
    license_state   CHAR(2)      NOT NULL,
    license_number  VARCHAR(20)  NOT NULL,
    address1        VARCHAR(100) NOT NULL,
    address2        VARCHAR(100) NULL,
    city            VARCHAR(60)  NOT NULL,
    state           CHAR(2)      NOT NULL,
    zip             CHAR(5)      NOT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_license (license_state, license_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE accounts (
    account_number      CHAR(16)      PRIMARY KEY,
    user_id             INT UNSIGNED  NOT NULL,
    type                ENUM('Checking', 'Savings') NOT NULL,
    balance             DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    interest_rate       DECIMAL(6,4)  NOT NULL,           -- annual, compounded yearly
    interest_applied_on DATE          NOT NULL,           -- last anniversary interest was paid
    created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY ix_accounts_user (user_id, created_at),
    CONSTRAINT fk_accounts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT ck_accounts_balance CHECK (balance >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE transactions (
    id                  CHAR(12)      PRIMARY KEY,
    account_number      CHAR(16)      NOT NULL,
    type                ENUM('Deposit', 'Withdrawal', 'Transfer Out', 'Transfer In', 'Interest') NOT NULL,
    status              ENUM('Pending', 'Processed', 'Denied') NOT NULL,
    amount              DECIMAL(15,2) NOT NULL,           -- negative when money leaves the account
    balance_before      DECIMAL(15,2) NULL,               -- NULL until a pending deposit is reviewed
    description         VARCHAR(255)  NOT NULL,
    related_transaction CHAR(12)      NULL,               -- the other side of a transfer
    created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY ix_transactions_account (account_number, created_at),
    KEY ix_transactions_status (status),
    CONSTRAINT fk_transactions_account FOREIGN KEY (account_number) REFERENCES accounts (account_number) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE employees (
    id              INT UNSIGNED  PRIMARY KEY,            -- random 9-digit employee id
    email           VARCHAR(254)  NOT NULL UNIQUE,
    password_hash   VARCHAR(255)  NULL,                   -- NULL until the new hire registers
    status          ENUM('Pending', 'Hired', 'Terminated') NOT NULL DEFAULT 'Pending',
    tier            TINYINT UNSIGNED NOT NULL,            -- 1 = teller, 2 = manager, 3 = admin
    manager_id      INT UNSIGNED  NULL,
    first_name      VARCHAR(50)   NULL,
    last_name       VARCHAR(50)   NULL,
    phone           CHAR(10)      NULL UNIQUE,
    ssn             CHAR(9)       NULL UNIQUE,
    license_state   CHAR(2)       NULL,
    license_number  VARCHAR(20)   NULL,
    address1        VARCHAR(100)  NULL,
    address2        VARCHAR(100)  NULL,
    city            VARCHAR(60)   NULL,
    state           CHAR(2)       NULL,
    zip             CHAR(5)       NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_employees_license (license_state, license_number),
    CONSTRAINT fk_employees_manager FOREIGN KEY (manager_id) REFERENCES employees (id) ON DELETE SET NULL,
    CONSTRAINT ck_employees_tier CHECK (tier BETWEEN 1 AND 3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE check_deposits (
    transaction_id  CHAR(12)      PRIMARY KEY,
    front_image     VARCHAR(100)  NOT NULL,               -- file name under storage/uploads/deposits
    back_image      VARCHAR(100)  NOT NULL,
    reviewed_by     INT UNSIGNED  NULL,
    reviewed_at     DATETIME      NULL,
    CONSTRAINT fk_deposits_transaction FOREIGN KEY (transaction_id) REFERENCES transactions (id) ON DELETE CASCADE,
    CONSTRAINT fk_deposits_reviewer FOREIGN KEY (reviewed_by) REFERENCES employees (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE support_tickets (
    id          CHAR(10)      PRIMARY KEY,
    name        VARCHAR(100)  NOT NULL,
    email       VARCHAR(254)  NOT NULL,
    question    VARCHAR(500)  NOT NULL,
    status      ENUM('Open', 'Resolved') NOT NULL DEFAULT 'Open',
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY ix_tickets_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
