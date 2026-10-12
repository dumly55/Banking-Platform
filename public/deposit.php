<?php
require __DIR__ . '/../src/bootstrap.php';
$userId = require_user();

if (is_post()) {
    verify_csrf();
    $amount = parse_amount(input('amount'));
    if (!$amount) back_with('deposit.php', 'Enter a valid amount greater than $0.00.');

    $error = create_deposit($userId, input('account'), $amount, $_FILES['frontCheck'] ?? [], $_FILES['backCheck'] ?? []);
    if ($error) back_with('deposit.php', $error);
    back_with('deposit.php', 'Your deposit of ' . money($amount) . ' is being processed. It will appear in your balance once approved.', 'success');
}

$accounts = user_accounts($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deposit | Bank of the Future</title>
    <link rel="icon" href="assets/img/favicon.ico">
    <link rel="stylesheet" href="assets/css/deposit.css">
    <link rel="stylesheet" href="assets/css/common.css">
    <script src="assets/js/forms.js" defer></script>
</head>
<body>
    <?php view('flash'); ?>
    <div id="form-container">
        <div class="container">
            <h1>Online Check Deposit</h1><br>
            <?php if (!$accounts): ?>
                <p>You need an account before you can deposit. <a href="dashboard.php">Open one from the home page.</a></p>
            <?php else: ?>
                <form action="deposit.php" method="post" enctype="multipart/form-data" style="display: inline-block;">
                    <?= csrf_field() ?>
                    <label for="account">To:</label>
                    <select name="account" id="account" required>
                        <?php foreach ($accounts as $acc): ?>
                            <option value="<?= e($acc['account_number']) ?>"><?= e($acc['type']) ?> (<?= e($acc['account_number']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <br><br>
                    <div id="form-section">
                        $<input type="text" name="amount" placeholder="Enter a dollar amount" id="amount" inputmode="decimal" data-filter="money" required>
                        <br><br>
                    </div>
                    Upload a picture of the front of your check:
                    <input type="file" name="frontCheck" accept="image/jpeg,image/png,image/gif,image/webp" required>
                    <br>
                    Upload a picture of the back of your check:
                    <input type="file" name="backCheck" accept="image/jpeg,image/png,image/gif,image/webp" required>
                    <br><br>
                    <input type="submit" value="Deposit" id="deposit">
                    <button type="reset" onclick="return confirm('Are you sure you want to reset the form?')">Reset</button>
                </form>
            <?php endif; ?>
            <br><br><br><br>
            <form action="dashboard.php" style="display: inline-block;">
                <button type="submit" id="home-button">Home</button>
            </form>
        </div>
    </div>
    <?php view('session_timeout'); ?>
</body>
</html>
