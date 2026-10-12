<?php
require __DIR__ . '/../src/bootstrap.php';
redirect_if_logged_in();

if (is_post()) {
    verify_csrf();
    $user = db_one('SELECT id, email, password_hash FROM users WHERE username = ?', [input('username')]);
    if (!$user || !password_verify($_POST['password'] ?? '', $user['password_hash'])) {
        back_with('login.php', 'Incorrect username or password.');
    }
    mfa_start('user_login', $user['email'], ['user_id' => $user['id']]);
    redirect('mfa.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Bank of the Future</title>
    <link rel="icon" href="assets/img/favicon.ico">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="assets/css/common.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>
<body>
    <?php view('flash'); ?>
    <div class="wrapper">
        <form action="login.php" method="post">
            <?= csrf_field() ?>
            <h1>Login</h1>
            <div class="input-box">
                <input type="text" placeholder="" name="username" autocomplete="username" required autofocus>
                <i class='bx bxs-user'></i>
                <span class="floating-label">Username</span>
            </div>
            <div class="input-box">
                <input type="password" placeholder="" name="password" autocomplete="current-password" required>
                <i class='bx bxs-lock-alt'></i>
                <span class="floating-label">Password</span>
            </div>
            <div class="remember-forgot">
                <a href="forgot.php">Forgot Password or Username?</a>
            </div>
            <button type="submit" class="btn">Login</button>
            <div class="register-link">
                <p>Don't have an account? <a href="register.php">Register</a></p>
            </div>
            <div class="register-link">
                <p><a href="index.html">Home</a></p>
            </div>
        </form>
    </div>
</body>
</html>
