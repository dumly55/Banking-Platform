<?php
require __DIR__ . '/../src/bootstrap.php';
$userId = require_user();

const MAX_PIN_FAILURES = 5;

if (is_post()) {
    verify_csrf();
    $pinHash = db_value('SELECT pin_hash FROM users WHERE id = ?', [$userId]);
    if (!password_verify($_POST['pin'] ?? '', $pinHash)) {
        $_SESSION['pin_failures'] = ($_SESSION['pin_failures'] ?? 0) + 1;
        if ($_SESSION['pin_failures'] >= MAX_PIN_FAILURES) {
            logout('Too many incorrect PIN attempts. Please log in again.', 'error');
            redirect('login.php');
        }
        back_with('atm.php', 'Incorrect PIN.');
    }
    unset($_SESSION['pin_failures']);

    $amount = parse_amount(input('amount'));
    if (!$amount) back_with('atm.php', 'Enter a valid amount greater than $0.00.');
    if ($error = withdraw($userId, input('account'), $amount)) back_with('atm.php', $error);
    back_with('atm.php', 'Withdrawal of ' . money($amount) . ' successful.', 'success');
}

$user = db_one('SELECT first_name, last_name FROM users WHERE id = ?', [$userId]);
$accounts = user_accounts($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATM | Bank of the Future</title>
    <link rel="icon" href="assets/img/favicon.ico">
    <link rel="stylesheet" href="assets/css/atm.css">
    <link rel="stylesheet" href="assets/css/common.css">
    <script src="assets/js/forms.js" defer></script>
</head>
<body>
    <?php view('flash'); ?>
    <h1>
        Choose a Card to Withdraw Money From:
        <?php view('user_nav', ['current' => 'atm.php']); ?>
    </h1>
    <br>
    <?php if (!$accounts): ?>
        <p class="empty-state">Open an account from the home page to get a debit card.</p>
    <?php endif; ?>

    <?php foreach ($accounts as $i => $acc):
        $background = $acc['type'] === 'Savings' ? 'savings_card.jpeg' : 'checking_card.jpg'; ?>
        <section>
            <div class="container">
                <button type="button" class="card front" onclick="togglePopup('popup-<?= $i ?>')"
                        style="background-image: url(assets/img/<?= $background ?>);">
                    <header>
                        <div class="logo">
                            <img src="assets/img/piggy.png" alt="">
                            <h5 id="mas">Bank of the Future Card</h5>
                        </div>
                        <img src="assets/img/chip.png" alt="" class="chip">
                    </header>
                    <div class="card-details">
                        <div class="name-number">
                            <h6>Card number</h6>
                            <h5 class="number"><?= e(format_card_number($acc['account_number'])) ?></h5>
                            <h5 class="name"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></h5>
                        </div>
                        <div class="VALIDDATE">
                            <h6 class="v">Balance</h6>
                            <h6 class="v"><?= money($acc['balance']) ?></h6>
                        </div>
                    </div>
                </button>
                <button type="button" class="card back-face" onclick="togglePopup('popup-<?= $i ?>')"
                        style="background-image: url(assets/img/<?= $background ?>);">
                    <h6>For customer service, please call our support number.</h6>
                    <span class="strip"></span>
                    <div class="sign"><i>***</i></div>
                    <div class="add">
                        <h6 style="color: aliceblue;">
                            <?= e($acc['type']) ?> Account Debit Card<br>Account Number:<br><?= e($acc['account_number']) ?>
                        </h6>
                    </div>
                </button>
            </div>
        </section><br>

        <div class="popup" id="popup-<?= $i ?>">
            <div class="overlay" onclick="togglePopup('popup-<?= $i ?>')"></div>
            <div class="content">
                <div class="close-btn" onclick="togglePopup('popup-<?= $i ?>')">&times;</div>
                <form action="atm.php" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="account" value="<?= e($acc['account_number']) ?>">
                    <h2 style="color: black;">ATM</h2>
                    <p style="color: black;"><?= e($acc['type']) ?> &middot; <?= money($acc['balance']) ?> available</p>
                    <label style="color: black;" for="pin-<?= $i ?>">PIN</label>
                    <input type="password" name="pin" id="pin-<?= $i ?>" minlength="4" maxlength="4" inputmode="numeric" data-filter="digits" required><br>
                    <label style="color: black;" for="amount-<?= $i ?>">Amount ($):</label>
                    <input type="text" name="amount" id="amount-<?= $i ?>" inputmode="decimal" data-filter="money" required><br><br>
                    <button class="btn" style="color: black;" type="submit">Withdraw</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>

    <img id="wallet" src="assets/img/wallet.webp" alt="">
    <?php view('session_timeout'); ?>
</body>
</html>
