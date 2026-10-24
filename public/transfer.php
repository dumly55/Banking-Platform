<?php
require __DIR__ . '/../src/bootstrap.php';
$userId = require_user();
if (!is_post()) redirect('dashboard.php');
verify_csrf();

$from = input('from');
$to = input('to');
$amount = parse_amount(input('amount'));

if ($error = transfer_problem($userId, $from, $to, $amount)) back_with('dashboard.php', $error);

// Moving money between your own accounts happens right away; sending to someone else needs an emailed code.
if (is_own_account($userId, $to)) {
    if ($error = transfer($userId, $from, $to, $amount)) back_with('dashboard.php', $error);
    back_with('dashboard.php', 'Transferred ' . money($amount) . " to account $to.", 'success');
}

$email = db_value('SELECT email FROM users WHERE id = ?', [$userId]);
mfa_start('transfer', $email, ['user_id' => $userId, 'from' => $from, 'to' => $to, 'amount' => $amount]);
redirect('mfa.php');
