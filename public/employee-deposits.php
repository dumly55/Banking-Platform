<?php
require __DIR__ . '/../src/bootstrap.php';
$me = require_employee();

if (is_post()) {
    verify_csrf();
    $decision = input('decision');
    if (!in_array($decision, ['approve', 'deny'], true)) redirect('employee-deposits.php');
    if (!review_deposit((int) $me['id'], input('transaction'), $decision === 'approve')) {
        back_with('employee-deposits.php', 'That deposit was already reviewed.');
    }
    back_with('employee-deposits.php', 'Deposit ' . ($decision === 'approve' ? 'approved.' : 'denied.'), 'success');
}

$deposits = db_all(
    "SELECT t.id, t.amount, t.account_number, t.created_at, u.first_name, u.last_name
     FROM transactions t
     JOIN check_deposits d ON d.transaction_id = t.id
     JOIN accounts a ON a.account_number = t.account_number
     JOIN users u ON u.id = a.user_id
     WHERE t.type = 'Deposit' AND t.status = 'Pending'
     ORDER BY t.created_at"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Deposits | Bank of the Future</title>
    <link rel="icon" href="assets/img/favicon.ico">
    <link rel="stylesheet" href="assets/css/employee.css">
    <link rel="stylesheet" href="assets/css/common.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script src="assets/js/lightbox.js" defer></script>
</head>
<body>
    <?php view('flash'); ?>
    <?php view('employee_nav', ['title' => 'Pending Deposits', 'current' => 'employee-deposits.php', 'employee' => $me]); ?>
    <br>
    <table>
        <caption>Pending Online Deposits (<?= count($deposits) ?>)</caption>
        <thead>
            <tr><th>Front of Check</th><th>Back of Check</th><th>Details</th><th>Approve/Deny</th></tr>
        </thead>
        <tbody>
            <?php foreach ($deposits as $d): ?>
                <tr>
                    <td><img src="check-image.php?id=<?= e($d['id']) ?>&amp;side=front" alt="Front of check" loading="lazy"></td>
                    <td><img src="check-image.php?id=<?= e($d['id']) ?>&amp;side=back" alt="Back of check" loading="lazy"></td>
                    <td class="details">
                        <strong><?= money($d['amount']) ?></strong><br>
                        <?= e("{$d['first_name']} {$d['last_name']}") ?><br>
                        Account <?= e($d['account_number']) ?><br>
                        <small><?= date('m/d/Y g:i A', strtotime($d['created_at'])) ?></small>
                    </td>
                    <td>
                        <form action="employee-deposits.php" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="transaction" value="<?= e($d['id']) ?>">
                            <button name="decision" type="submit" value="approve">Approve</button>
                            <button name="decision" type="submit" value="deny">Deny</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php view('session_timeout'); ?>
</body>
</html>
