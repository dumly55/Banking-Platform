<?php
require __DIR__ . '/../src/bootstrap.php';

// Reached only after verifying an emailed code on mfa.php.
$reset = $_SESSION['password_reset'] ?? null;
if (!$reset || time() > $reset['expires']) {
    unset($_SESSION['password_reset']);
    back_with('forgot.php', 'Your reset session expired. Please start again.');
}
$loginPage = $reset['table'] === 'employees' ? 'employee-login.php' : 'login.php';

if (is_post()) {
    verify_csrf();
    $password = $_POST['password'] ?? '';
    if ($problem = password_problem($password)) back_with('reset-password.php', $problem);
    if ($password !== ($_POST['cpassword'] ?? '')) back_with('reset-password.php', 'Passwords do not match.');

    db_exec("UPDATE {$reset['table']} SET password_hash = ? WHERE id = ?", [password_hash($password, PASSWORD_DEFAULT), $reset['id']]);
    logout('Password reset successful. You can now log in.', 'success');
    redirect($loginPage);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Bank of the Future</title>
    <link rel="icon" href="assets/img/lock.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/forgot.css">
    <link rel="stylesheet" href="assets/css/common.css">
    <script src="assets/js/forms.js" defer></script>
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
                        <h2><b>Reset Password</b></h2>
                        <p>Choose a new password (at least 8 characters, with a letter and a number):</p>
                        <form action="reset-password.php" method="post" autocomplete="off">
                            <?= csrf_field() ?>
                            <div class="form-group">
                                <input id="password" name="password" placeholder="New Password" class="form-control" type="password"
                                       minlength="8" pattern="(?=.*[A-Za-z])(?=.*\d).{8,}" required autofocus>
                            </div>
                            <div class="form-group">
                                <input name="cpassword" placeholder="Confirm New Password" class="form-control" type="password"
                                       data-match="#password" required>
                                <span class="match-message"></span>
                            </div>
                            <div class="form-group">
                                <button class="btn btn-lg btn-primary btn-block" type="submit">Reset Password</button>
                            </div>
                            <div class="form-group">
                                <a href="logout.php" class="btn btn-lg btn-default btn-block">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
