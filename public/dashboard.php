<?php
require __DIR__ . '/../src/bootstrap.php';
$userId = require_user();

$firstName = db_value('SELECT first_name FROM users WHERE id = ?', [$userId]);
$accounts = user_accounts($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Accounts | Bank of the Future</title>
    <link rel="icon" href="assets/img/favicon.ico">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/common.css">
    <script src="assets/js/forms.js" defer></script>
</head>
<body>
    <?php view('flash'); ?>
    <h1>
        <span style="font-weight: bold;">Welcome, <?= e($firstName) ?>!</span>
        <?php view('user_nav', ['current' => 'dashboard.php']); ?>
        <div class="lbtn"><a onclick="togglePopup('popup-transfer')" style="cursor: pointer;" class="log logbutton">Transfer</a></div>
        <div class="lbtn"><a onclick="togglePopup('popup-open')" style="cursor: pointer;" class="log logbutton">Open an Account</a></div>
    </h1>

    <form action="account.php" method="get">
        <div class="account">
            <br>
            <p class="name"><span>Account(s)</span></p><br>
            <?php foreach ($accounts as $acc): ?>
                <button name="number" value="<?= e($acc['account_number']) ?>" type="submit">
                    <span class="bal"><?= money($acc['balance']) ?></span>
                    <span class="num"><?= e($acc['type']) ?> Account<br>(#<?= e($acc['account_number']) ?>)</span>
                </button><br>
            <?php endforeach; ?>
            <?php if (!$accounts): ?>
                <p class="empty-state">You don't have any accounts yet. Use <b>Open an Account</b> to get started.</p>
            <?php endif; ?>
        </div>
    </form>

    <div class="popup" id="popup-open">
        <div class="overlay" onclick="togglePopup('popup-open')"></div>
        <div class="content">
            <div class="close-btn" onclick="togglePopup('popup-open')">&times;</div>
            <form action="open-account.php" method="post">
                <?= csrf_field() ?>
                <div class="container">
                    <h2>Choose an Account</h2>
                    <input type="radio" id="checking" name="type" value="Checking" required>
                    <label for="checking">Checking Account</label> <br>
                    <input type="radio" id="savings" name="type" value="Savings" required>
                    <label for="savings">Savings Account</label> <br>
                    <button type="submit" class="btn">Open</button>
                </div>
            </form>
        </div>
    </div>

    <div class="popup" id="popup-transfer">
        <div class="overlay" onclick="togglePopup('popup-transfer')"></div>
        <div class="content">
            <div class="close-btn" onclick="togglePopup('popup-transfer')">&times;</div>
            <form action="transfer.php" method="post">
                <?= csrf_field() ?>
                <h2>Transfer</h2>
                <label for="from">From:</label>
                <select name="from" id="from" required>
                    <?php foreach ($accounts as $acc): ?>
                        <option value="<?= e($acc['account_number']) ?>"><?= e($acc['type']) ?> (<?= e($acc['account_number']) ?>) &ndash; <?= money($acc['balance']) ?></option>
                    <?php endforeach; ?>
                </select>
                <br>
                <label for="to">To (Account #):</label>
                <input type="text" name="to" id="to" minlength="16" maxlength="16" inputmode="numeric" data-filter="digits" required><br>
                <label for="amount">Amount ($):</label>
                <input type="text" name="amount" id="amount" inputmode="decimal" data-filter="money" required><br><br>
                <button type="submit" class="btn">Transfer</button>
            </form>
        </div>
    </div>

    <?php view('session_timeout'); ?>
</body>
</html>
