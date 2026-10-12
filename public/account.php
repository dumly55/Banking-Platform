<?php
require __DIR__ . '/../src/bootstrap.php';
$userId = require_user();

$account = user_account($userId, input('number'));
if (!$account) back_with('dashboard.php', 'Account not found.');

$transactions = db_all(
    'SELECT * FROM transactions WHERE account_number = ? ORDER BY created_at DESC, id DESC',
    [$account['account_number']]
);
$statusColor = ['Processed' => 'green', 'Denied' => 'red', 'Pending' => 'gray'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Info | Bank of the Future</title>
    <link rel="icon" href="assets/img/favicon.ico">
    <link rel="stylesheet" href="assets/css/account.css">
    <link rel="stylesheet" href="assets/css/common.css">
</head>
<body>
    <?php view('flash'); ?>
    <h1>
        Account Information
        <?php view('user_nav'); ?>
    </h1>
    <h2><?= e($account['type']) ?> Account (<?= e($account['account_number']) ?>)</h2>
    <p id="balance">Balance: <?= money($account['balance']) ?></p>
    <p id="time_created">
        Opened: <?= date('m/d/Y g:i A', strtotime($account['created_at'])) ?>
        | Interest: <?= rtrim(rtrim(number_format($account['interest_rate'] * 100, 2), '0'), '.') ?>% APY
    </p>
    <div class="account">
        <p class="name">Transaction(s)</p><br>
        <?php foreach ($transactions as $t): ?>
            <a class="acc">
                <span class="bal"><?= money($t['amount']) ?></span>
                <span class="description"><?= e($t['description']) ?></span>
                <span class="status" style="color: <?= $statusColor[$t['status']] ?>"><?= e($t['status']) ?></span>
                <span class="date"><?= date('m/d/Y | g:i:s A', strtotime($t['created_at'])) ?></span>
                <span class="obal"><?= $t['balance_before'] === null ? '' : 'Prior balance: ' . money($t['balance_before']) ?></span>
                <span class="tnum">Transaction #: <?= e($t['id']) ?></span>
            </a><br>
        <?php endforeach; ?>
        <?php if (!$transactions): ?>
            <p class="empty-state">No transactions yet.</p>
        <?php endif; ?>
        <br>
    </div>
    <?php view('session_timeout'); ?>
</body>
</html>
