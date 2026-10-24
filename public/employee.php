<?php
require __DIR__ . '/../src/bootstrap.php';
$me = require_employee();

/** Employees $me may terminate: admins (tier 3) manage everyone below them, managers only their own hires. */
function terminable_employees(array $me): array
{
    if ($me['tier'] >= 3) {
        return db_all("SELECT * FROM employees WHERE tier < ? AND status <> 'Terminated' ORDER BY tier DESC, last_name", [$me['tier']]);
    }
    return db_all("SELECT * FROM employees WHERE manager_id = ? AND tier < ? AND status <> 'Terminated' ORDER BY last_name", [$me['id'], $me['tier']]);
}

if (is_post()) {
    verify_csrf();
    $me = require_employee(2);

    if (input('action') === 'hire') {
        $email = input('email');
        $tier = (int) input('tier');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) back_with('employee.php', 'Enter a valid email address.');
        if ($tier < 1 || $tier >= $me['tier']) back_with('employee.php', 'You can only hire into tiers below your own.');

        $existing = db_one('SELECT id, status, password_hash FROM employees WHERE email = ?', [$email]);
        if ($existing && $existing['status'] !== 'Terminated') {
            back_with('employee.php', $existing['status'] === 'Hired'
                ? 'That person already works at Bank of the Future.'
                : 'That person already has a pending offer (employee ID ' . $existing['id'] . ').');
        }

        if ($existing) {
            // Rehire: people who registered before can log straight back in.
            $id = $existing['id'];
            $status = $existing['password_hash'] ? 'Hired' : 'Pending';
            db_exec('UPDATE employees SET status = ?, tier = ?, manager_id = ? WHERE id = ?', [$status, $tier, $me['id'], $id]);
            $body = $status === 'Hired'
                ? "Welcome back to Bank of the Future! You can log in again with your employee ID ($id) and existing password."
                : "Welcome back to Bank of the Future! Use your employee ID ($id) to register at " . config('url') . '/employee-register.php';
        } else {
            $id = unique_number('employees', 'id', 9);
            db_exec("INSERT INTO employees (id, email, status, tier, manager_id) VALUES (?, ?, 'Pending', ?, ?)", [$id, $email, $tier, $me['id']]);
            $body = "Congratulations on joining Bank of the Future! Your employee ID is $id.\n\n"
                . 'Use it at ' . config('url') . '/employee-register.php to set up your employee account.';
        }
        send_mail($email, 'Job Offer from Bank of the Future', $body);
        back_with('employee.php', "Offer sent to $email (employee ID $id).", 'success');
    }

    if (input('action') === 'terminate') {
        $targetId = (int) input('employee');
        $allowed = array_map('intval', array_column(terminable_employees($me), 'id'));
        if (!in_array($targetId, $allowed, true)) back_with('employee.php', 'You cannot terminate that employee.');
        db_exec("UPDATE employees SET status = 'Terminated' WHERE id = ?", [$targetId]);
        back_with('employee.php', "Employee $targetId has been terminated.", 'success');
    }
    redirect('employee.php');
}

$accounts = db_all(
    'SELECT a.account_number, a.balance, a.type, u.username, u.first_name, u.last_name
     FROM accounts a JOIN users u ON u.id = a.user_id
     ORDER BY a.balance DESC'
);
$terminable = $me['tier'] > 1 ? terminable_employees($me) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee | Bank of the Future</title>
    <link rel="icon" href="assets/img/favicon.ico">
    <link rel="stylesheet" href="assets/css/employee.css">
    <link rel="stylesheet" href="assets/css/common.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script src="assets/js/forms.js" defer></script>
</head>
<body>
    <?php view('flash'); ?>
    <?php view('employee_nav', [
        'title' => "Welcome to work, {$me['first_name']} {$me['last_name']}!",
        'current' => 'employee.php',
        'employee' => $me,
    ]); ?>
    <div class="toolbar">
        <a href="register.php" class="log logbutton">Register New Customer</a>
    </div>
    <table>
        <caption>Total Bank Accounts (<?= count($accounts) ?>)</caption>
        <thead>
            <tr><th>Account Number</th><th>Owner</th><th>Balance</th><th>Type</th></tr>
        </thead>
        <tbody>
            <?php foreach ($accounts as $a): ?>
                <tr>
                    <td><?= e($a['account_number']) ?></td>
                    <td><?= e("{$a['first_name']} {$a['last_name']}") ?> (Username: <?= e($a['username']) ?>)</td>
                    <td><?= money($a['balance']) ?></td>
                    <td><?= e($a['type']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($me['tier'] > 1): ?>
        <div class="popup" id="popup-hire">
            <div class="overlay" onclick="togglePopup('popup-hire')"></div>
            <div class="content">
                <div class="close-btn" onclick="togglePopup('popup-hire')">&times;</div>
                <form action="employee.php" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="hire">
                    <h2>Hire</h2><br>
                    <label>Email: <input name="email" type="email" required></label><br>
                    <label>Tier:
                        <select name="tier" required>
                            <?php for ($t = $me['tier'] - 1; $t >= 1; $t--): ?>
                                <option value="<?= $t ?>"><?= $t ?></option>
                            <?php endfor; ?>
                        </select>
                    </label><br><br>
                    <button type="submit" class="btn">Hire</button>
                </form>
            </div>
        </div>

        <div class="popup" id="popup-terminate">
            <div class="overlay" onclick="togglePopup('popup-terminate')"></div>
            <div class="content">
                <div class="close-btn" onclick="togglePopup('popup-terminate')">&times;</div>
                <form action="employee.php" method="post" onsubmit="return confirm('Terminate this employee?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="terminate">
                    <h2>Terminate</h2><br>
                    <?php if ($terminable): ?>
                        <label>Select Employee:
                            <select name="employee" required>
                                <?php foreach ($terminable as $t): ?>
                                    <option value="<?= $t['id'] ?>">
                                        <?= $t['status'] === 'Hired'
                                            ? e("{$t['first_name']} {$t['last_name']} (ID: {$t['id']}, Tier: {$t['tier']})")
                                            : e("ID: {$t['id']} (Pending Tier {$t['tier']} offer, {$t['email']})") ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label><br><br>
                        <button type="submit" class="btn">Terminate</button>
                    <?php else: ?>
                        <p>There is no one you can terminate.</p>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php view('session_timeout'); ?>
</body>
</html>
