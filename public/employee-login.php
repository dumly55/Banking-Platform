<?php
require __DIR__ . '/../src/bootstrap.php';
redirect_if_logged_in();

if (is_post()) {
    verify_csrf();
    $employee = db_one('SELECT id, email, password_hash, status FROM employees WHERE id = ?', [input('employee_id')]);
    if (!password_matches($_POST['password'] ?? '', $employee['password_hash'] ?? null)) {
        back_with('employee-login.php', 'Incorrect employee ID or password.');
    }
    // Only tell someone they were terminated once they've proven who they are.
    if ($employee['status'] !== 'Hired') back_with('employee-login.php', 'Sorry. You are no longer with the company.');
    mfa_start('employee_login', $employee['email'], ['employee_id' => $employee['id']]);
    redirect('mfa.php');
}

$keys = [...str_split('1234567890abcdefghijklmnopqrstuvwxyz'), '^', '-', '_', '@', '+', '&', '*', '%', '$', '#', '!'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Login | Bank of the Future</title>
    <link rel="icon" href="assets/img/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="assets/css/employee-auth.css">
    <link rel="stylesheet" href="assets/css/common.css">
    <script src="assets/js/forms.js" defer></script>
    <script src="assets/js/virtual-keyboard.js" defer></script>
</head>
<body>
    <?php view('flash'); ?>
    <div id="wrapper">
        <form action="employee-login.php" id="form-login" method="post">
            <?= csrf_field() ?>
            <h1 class="form-heading">Employee Login</h1>
            <div class="form-group">
                <input type="text" name="employee_id" class="form-input" placeholder="Employee ID" maxlength="10"
                       inputmode="numeric" data-filter="digits" autocomplete="username" required autofocus>
            </div>
            <div class="form-group">
                <br>
                <input type="password" name="password" class="form-input" placeholder="Password" autocomplete="current-password" required>
                <i class="fas fa-eye" id="togglePassword" title="Show password"></i>
            </div>
            <div class="text-center p-t-90">
                <a class="txt1" href="employee-register.php">New Employee? Register</a>
            </div>
            <div class="text-center p-t-90">
                <a class="txt1" href="forgot.php?for=employee">Forgot Password or Employee ID</a>
            </div>
            <div class="container-login100-form-btn">
                <button class="login100-form-btn">Login</button>
            </div>
            <br><br>
            <details class="hidden-xs virtual-keyboard">
                <summary class="text-danger text-right">Virtual Keyboard</summary>
                <ul class="keyboard">
                    <?php foreach ($keys as $key): ?>
                        <li class="char<?= $key === '@' ? ' at' : '' ?>"><?= e($key) ?></li>
                    <?php endforeach; ?>
                    <li class="backspace last">&#9003;</li>
                    <li class="capslock">CAPS</li>
                </ul>
            </details>
        </form>
    </div>
    <footer>
        <p><a href="index.html">Go back to the Homepage</a></p>
    </footer>
</body>
</html>
