<?php
// Creates the database and tables, and optionally loads demo data.
//
//   php database/setup.php            create the database + tables (refuses if tables already exist)
//   php database/setup.php --seed     ...and add demo customers, employees and transactions
//   php database/setup.php --fresh    drop and recreate everything (destroys existing data!)

if (PHP_SAPI !== 'cli') exit("Run this from the command line.\n");

require __DIR__ . '/../src/bootstrap.php';

$fresh = in_array('--fresh', $argv, true);
$seed = in_array('--seed', $argv, true) || $fresh;
$c = config('db');

$server = new PDO("mysql:host={$c['host']};port={$c['port']};charset=utf8mb4", $c['user'], $c['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$server->exec("CREATE DATABASE IF NOT EXISTS `{$c['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$existing = db_value('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ?', [$c['name']]);
if ($existing && !$fresh) {
    exit("Database '{$c['name']}' already has tables. Re-run with --fresh to wipe and recreate it.\n");
}

foreach (preg_split('/;\s*\n/', file_get_contents(__DIR__ . '/schema.sql')) as $statement) {
    $statement = trim(preg_replace('/^\s*--.*$/m', '', $statement));
    if ($statement !== '') db()->exec($statement);
}
echo "Created tables in '{$c['name']}'.\n";

if (!$seed) exit;

// ---- Demo data (all passwords: Password123) ----

$password = password_hash('Password123', PASSWORD_DEFAULT);

$employees = [
    // id, email, tier, manager, first, last, phone, ssn, license
    [100000001, 'admin@bankofthefuture.test',   3, null,      'Alex',   'Morgan', '4085550101', '900000001', 'A1000001'],
    [100000002, 'manager@bankofthefuture.test', 2, 100000001, 'Jordan', 'Lee',    '4085550102', '900000002', 'A1000002'],
    [100000003, 'teller@bankofthefuture.test',  1, 100000002, 'Sam',    'Rivera', '4085550103', '900000003', 'A1000003'],
];
foreach ($employees as [$id, $email, $tier, $manager, $first, $last, $phone, $ssn, $license]) {
    db_exec(
        "INSERT INTO employees (id, email, password_hash, status, tier, manager_id, first_name, last_name, phone, ssn,
                                license_state, license_number, address1, city, state, zip)
         VALUES (?, ?, ?, 'Hired', ?, ?, ?, ?, ?, ?, 'CA', ?, '1 Washington Sq', 'San Jose', 'CA', '95192')",
        [$id, $email, $password, $tier, $manager, $first, $last, $phone, $ssn, $license]
    );
}
// A pending offer, so the Terminate list and new-hire registration have something to show.
db_exec("INSERT INTO employees (id, email, status, tier, manager_id) VALUES (100000004, 'newhire@bankofthefuture.test', 'Pending', 1, 100000002)");

$customers = [
    // username, pin, first, last, email, phone, ssn, license
    ['demo', '1234', 'Taylor', 'Chen', 'demo@bankofthefuture.test',  '4085550111', '800000001', 'D1000001'],
    ['jdoe', '4321', 'Jamie',  'Doe',  'jamie@bankofthefuture.test', '4085550112', '800000002', 'D1000002'],
];
$userIds = [];
foreach ($customers as [$username, $pin, $first, $last, $email, $phone, $ssn, $license]) {
    db_exec(
        "INSERT INTO users (username, password_hash, pin_hash, first_name, last_name, email, phone, ssn,
                            license_state, license_number, address1, city, state, zip)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'CA', ?, '100 Market St', 'San Jose', 'CA', '95113')",
        [$username, $password, password_hash($pin, PASSWORD_DEFAULT), $first, $last, $email, $phone, $ssn, $license]
    );
    $userIds[$username] = (int) db()->lastInsertId();
}

$accounts = [
    // number, owner, type, opened (days ago)
    ['4100123456780001', 'demo', 'Checking', 90],
    ['4200123456780002', 'demo', 'Savings',  90],
    ['4100987654320003', 'jdoe', 'Checking', 60],
];
foreach ($accounts as [$number, $owner, $type, $daysAgo]) {
    db_exec(
        'INSERT INTO accounts (account_number, user_id, type, interest_rate, interest_applied_on, created_at)
         VALUES (?, ?, ?, ?, CURDATE() - INTERVAL ? DAY, NOW() - INTERVAL ? DAY)',
        [$number, $userIds[$owner], $type, ACCOUNT_TYPES[$type][1], $daysAgo, $daysAgo]
    );
}

// History: [account, type, amount, description, days ago, related account for transfers]
$history = [
    ['4100123456780001', 'Deposit',    '2500.00', 'Mobile check deposit', 88],
    ['4200123456780002', 'Deposit',    '8000.00', 'Mobile check deposit', 87],
    ['4100987654320003', 'Deposit',    '1200.00', 'Mobile check deposit', 59],
    ['4100123456780001', 'Withdrawal', '-120.00', 'ATM withdrawal',       40],
    ['4100123456780001', 'Withdrawal', '-60.00',  'ATM withdrawal',       12],
];
foreach ($history as [$number, $type, $amount, $description, $daysAgo]) {
    $before = db_value('SELECT balance FROM accounts WHERE account_number = ?', [$number]);
    $id = record_transaction($number, $type, 'Processed', $amount, $before, $description);
    db_exec('UPDATE transactions SET created_at = NOW() - INTERVAL ? DAY WHERE id = ?', [$daysAgo, $id]);
    db_exec('UPDATE accounts SET balance = balance + ? WHERE account_number = ?', [$amount, $number]);
}
transfer($userIds['demo'], '4200123456780002', '4100123456780001', '500.00');
transfer($userIds['demo'], '4100123456780001', '4100987654320003', '75.50');

$tickets = [
    ['Morgan Bailey', 'morgan@example.com', 'How long does a mobile check deposit usually take to clear?', 'Open'],
    ['Chris Patel',   'chris@example.com',  'Can I open a joint savings account with my spouse?', 'Open'],
    ['Riley Kim',     'riley@example.com',  'I forgot my username, how do I recover it?', 'Resolved'],
];
foreach ($tickets as [$name, $email, $question, $status]) {
    db_exec('INSERT INTO support_tickets (id, name, email, question, status) VALUES (?, ?, ?, ?, ?)',
        [unique_number('support_tickets', 'id', 10), $name, $email, $question, $status]);
}

echo <<<TXT
Seeded demo data. Every password is Password123.
  Customers:  demo (PIN 1234), jdoe (PIN 4321)
  Employees:  100000001 (admin, tier 3), 100000002 (manager, tier 2), 100000003 (teller, tier 1)
  New hire:   employee ID 100000004 / newhire@bankofthefuture.test can register at employee-register.php

TXT;
