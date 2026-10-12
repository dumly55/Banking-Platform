<?php
require __DIR__ . '/../src/bootstrap.php';
redirect_if_logged_in();

$forEmployee = input('for') === 'employee';
$self = $forEmployee ? 'forgot.php?for=employee' : 'forgot.php';
$loginPage = $forEmployee ? 'employee-login.php' : 'login.php';

if (is_post()) {
    verify_csrf();
    $email = input('email');
    $account = $forEmployee
        ? db_one("SELECT id, email FROM employees WHERE email = ? AND status = 'Hired'", [$email])
        : db_one('SELECT id, email, username FROM users WHERE email = ?', [$email]);

    if (input('action') === 'reset') {
        // Unknown emails get the same code screen (but no email), so this form can't be used to find customers.
        mfa_start($forEmployee ? 'reset_employee' : 'reset_user', $account['email'] ?? null, ['id' => $account['id'] ?? null]);
        redirect('mfa.php');
    }

    // Recover username / employee id. Same response either way so emails can't be probed.
    if ($account) {
        $forEmployee
            ? send_mail($account['email'], 'Your Employee ID', "Your Bank of the Future employee ID is: {$account['id']}")
            : send_mail($account['email'], 'Your Username', "Your Bank of the Future username is: {$account['username']}");
    }
    back_with($loginPage, 'If that email is registered, we sent you an email with your ' . ($forEmployee ? 'employee ID.' : 'username.'), 'info');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Bank of the Future</title>
    <link rel="icon" href="assets/img/lock.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/forgot.css">
    <link rel="stylesheet" href="assets/css/common.css">
</head>
<body>
    <?php view('flash'); ?>
    <div class="form-gap"></div>
    <div class="container">
        <div class="row">
            <div class="col-md-4 col-md-offset-4">
                <div class="panel panel-default">
                    <div class="panel-body text-center">
                        <h3><i class="fa fa-lock fa-4x" style="color: gray;"></i></h3>
                        <h2><b>Forgot Password?</b></h2>
                        <p>Enter your email address to reset your password or recover your <?= $forEmployee ? 'employee ID' : 'username' ?>.</p>
                        <form action="<?= e($self) ?>" method="post" autocomplete="off">
                            <?= csrf_field() ?>
                            <div class="form-group">
                                <div class="input-group">
                                    <span class="input-group-addon"><i class="glyphicon glyphicon-envelope color-blue"></i></span>
                                    <input name="email" placeholder="email address" class="form-control" type="email" required autofocus>
                                </div>
                            </div>
                            <div class="form-group">
                                <button name="action" value="reset" class="btn btn-lg btn-primary btn-block" type="submit">Reset Password</button>
                            </div>
                            <div class="form-group">
                                <button name="action" value="recover" class="btn btn-lg btn-primary btn-block" type="submit">
                                    Forgot <?= $forEmployee ? 'Employee ID' : 'Username' ?>
                                </button>
                            </div>
                            <div class="form-group">
                                <a href="<?= $loginPage ?>" class="btn btn-lg btn-default btn-block">Back to Login</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
