<?php
require __DIR__ . '/../src/bootstrap.php';

$mfa = mfa_pending();
if (!$mfa) redirect(current_user_id() ? 'dashboard.php' : (current_employee_id() ? 'employee.php' : 'login.php'));

// Where "Back" (or a failed challenge) leads for each purpose.
$exitPage = [
    'user_login'     => 'login.php',
    'employee_login' => 'employee-login.php',
    'transfer'       => 'dashboard.php',
    'reset_user'     => 'forgot.php',
    'reset_employee' => 'forgot.php?for=employee',
][$mfa['purpose']];

if ($mfa['purpose'] === 'transfer') require_user();

if (isset($_GET['cancel'])) {
    unset($_SESSION['mfa']);
    redirect($exitPage);
}

if (is_post() && input('action') === 'resend') {
    verify_csrf();
    if (!mfa_send_code()) back_with('mfa.php', 'Too many codes requested. Please go back and start again.');
    back_with('mfa.php', 'A new code was sent to your email.', 'info');
}

if (is_post()) {
    verify_csrf();
    $verified = mfa_verify(input('code'));
    if (!$verified) {
        if (mfa_pending()) back_with('mfa.php', 'The code you entered was invalid.');
        back_with($exitPage, 'That code expired or had too many wrong attempts. Please start again.');
    }

    $data = $verified['data'];
    switch ($verified['purpose']) {
        case 'user_login':
            login_as('user', $data['user_id']);
            apply_interest($data['user_id']);
            redirect('dashboard.php');

        case 'employee_login':
            login_as('employee', $data['employee_id']);
            apply_interest();
            redirect('employee.php');

        case 'transfer':
            $error = transfer_problem($data['user_id'], $data['from'], $data['to'], $data['amount'])
                ?? transfer($data['user_id'], $data['from'], $data['to'], $data['amount']);
            if ($error) back_with('dashboard.php', $error);
            back_with('dashboard.php', 'Transferred ' . money($data['amount']) . " to account {$data['to']}.", 'success');

        case 'reset_user':
        case 'reset_employee':
            $_SESSION['password_reset'] = [
                'table'   => $verified['purpose'] === 'reset_user' ? 'users' : 'employees',
                'id'      => $data['id'],
                'expires' => time() + MFA_TTL,
            ];
            redirect('reset-password.php');
    }
}

$devCode = $mfa['dev_code'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification | Bank of the Future</title>
    <link rel="icon" href="assets/img/favicon.ico">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="assets/css/common.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script src="assets/js/forms.js" defer></script>
</head>
<body>
    <?php view('flash'); ?>
    <div class="wrapper">
        <form action="mfa.php" method="post">
            <?= csrf_field() ?>
            <h1>Multi-Factor Authentication</h1>
            <?php if ($devCode): ?>
                <p class="dev-note">Development mode (no SMTP configured): your code is <strong><?= e($devCode) ?></strong></p>
            <?php endif; ?>
            <div class="input-box">
                <input type="text" placeholder="" name="code" maxlength="6" pattern="\d{6}" inputmode="numeric"
                       autocomplete="one-time-code" data-filter="digits" required autofocus>
                <i class='bx bx-dialpad'></i>
                <span class="floating-label">Enter the 6-digit code sent to your email</span>
            </div>
            <button type="submit" class="btn">Submit</button>
        </form>
        <form action="mfa.php" method="post" class="register-link">
            <?= csrf_field() ?>
            <p>Didn't receive a code? <button type="submit" name="action" value="resend" class="link-button">Send it again.</button></p>
        </form>
        <div class="register-link">
            <p><a href="mfa.php?cancel=1">Back</a></p>
        </div>
    </div>
</body>
</html>
