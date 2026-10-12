<?php
// New hires register in two steps: confirm the employee ID from their offer email, then fill in their details.
require __DIR__ . '/../src/bootstrap.php';
redirect_if_logged_in();

if (isset($_GET['cancel'])) {
    unset($_SESSION['employee_signup']);
    redirect('employee-login.php');
}

$signupId = $_SESSION['employee_signup'] ?? null;
$old = [];
$errors = [];

if (is_post() && input('step') === 'verify') {
    verify_csrf();
    $id = db_value(
        "SELECT id FROM employees WHERE id = ? AND email = ? AND status = 'Pending' AND password_hash IS NULL",
        [input('employee_id'), input('email')]
    );
    if (!$id) back_with('employee-register.php', 'No pending offer matches that employee ID and email.');
    $_SESSION['employee_signup'] = $id;
    redirect('employee-register.php');
}

if (is_post() && $signupId) {
    verify_csrf();
    $old = collect_input(PERSON_FIELDS);
    $errors = [...person_errors($old), ...password_errors()];

    if (!$errors && db_value(
        'SELECT 1 FROM employees WHERE id <> ? AND (ssn = ? OR phone = ? OR (license_state = ? AND license_number = ?))',
        [$signupId, $old['ssn'], $old['phone'], $old['lstate'], $old['license']]
    )) {
        $errors[] = 'Registration unsuccessful: those details belong to another employee.';
    }

    if (!$errors) {
        db_exec(
            "UPDATE employees SET password_hash = ?, status = 'Hired', first_name = ?, last_name = ?, phone = ?, ssn = ?,
                license_state = ?, license_number = ?, address1 = ?, address2 = ?, city = ?, state = ?, zip = ?
             WHERE id = ? AND status = 'Pending'",
            [
                password_hash($_POST['password'], PASSWORD_DEFAULT), $old['first'], $old['last'], $old['phone'], $old['ssn'],
                $old['lstate'], $old['license'], $old['address'], $old['address2'] ?: null, $old['city'], $old['state'], $old['zip'],
                $signupId,
            ]
        );
        unset($_SESSION['employee_signup']);
        back_with('employee-login.php', "Registration successful. Log in with employee ID $signupId.", 'success');
    }
}

function emp_field(array $old, string $name, string $placeholder, string $attrs = ''): void
{
    echo '<div class="form-group"><input class="form-input" id="' . $name . '" name="' . $name . '" placeholder="'
        . $placeholder . '" value="' . e($old[$name] ?? '') . '" ' . $attrs . '></div><br>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Registration | Bank of the Future</title>
    <link rel="icon" href="assets/img/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/employee-auth.css">
    <link rel="stylesheet" href="assets/css/common.css">
    <script src="assets/js/forms.js" defer></script>
</head>
<body>
    <?php view('flash'); ?>
    <div id="wrapper">
        <?php if (!$signupId): ?>
            <form action="employee-register.php" method="post" id="form-login">
                <?= csrf_field() ?>
                <input type="hidden" name="step" value="verify">
                <h1 class="form-heading">New Employee</h1>
                <p class="form-hint">Enter the employee ID from your offer email.</p>
                <div class="form-group">
                    <input type="text" name="employee_id" class="form-input" placeholder="Employee ID" maxlength="10"
                           inputmode="numeric" data-filter="digits" required autofocus>
                </div><br>
                <div class="form-group">
                    <input type="email" name="email" class="form-input" placeholder="Email the offer was sent to" required>
                </div>
                <div class="container-login100-form-btn">
                    <button type="submit" class="login100-form-btn">Continue</button>
                </div>
                <div class="text-center p-t-90">
                    <a class="txt1" href="employee-login.php">Back to Login</a>
                </div>
            </form>
        <?php else: ?>
            <form action="employee-register.php" method="post" id="form-login">
                <?= csrf_field() ?>
                <h1 class="form-heading">Employee Registration</h1>
                <p class="form-hint">Employee ID <?= e($signupId) ?></p>
                <?php if ($errors): ?>
                    <ul class="form-errors">
                        <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php emp_field($old, 'first', 'First Name', 'type="text" maxlength="50" required'); ?>
                <?php emp_field($old, 'last', 'Last Name', 'type="text" maxlength="50" required'); ?>
                <div class="form-group">
                    <input type="password" id="password" name="password" class="form-input" placeholder="Password (8+ characters, a letter and a number)"
                           minlength="8" pattern="(?=.*[A-Za-z])(?=.*\d).{8,}" required>
                </div><br>
                <div class="form-group">
                    <input type="password" name="cpassword" class="form-input" placeholder="Confirm Password" data-match="#password" required>
                    <span class="match-message"></span>
                </div><br>
                <?php emp_field($old, 'phone', 'Phone Number (10 digits)', 'type="text" maxlength="10" pattern="\d{10}" inputmode="numeric" data-filter="digits" required'); ?>
                <?php emp_field($old, 'address', 'Address Line 1', 'type="text" maxlength="100" required'); ?>
                <?php emp_field($old, 'address2', 'Address Line 2 (Optional)', 'type="text" maxlength="100"'); ?>
                <?php emp_field($old, 'city', 'City', 'type="text" maxlength="60" data-filter="letters" required'); ?>
                <?php emp_field($old, 'state', 'State (ex: CA, NY, NC)', 'type="text" maxlength="2" pattern="[A-Z]{2}" data-filter="upper" required'); ?>
                <?php emp_field($old, 'zip', 'Zip Code', 'type="text" maxlength="5" pattern="\d{5}" inputmode="numeric" data-filter="digits" required'); ?>
                <?php emp_field($old, 'lstate', 'License State (ex: CA, NY, NC)', 'type="text" maxlength="2" pattern="[A-Z]{2}" data-filter="upper" required'); ?>
                <?php emp_field($old, 'license', 'License Number', 'type="text" maxlength="20" required'); ?>
                <?php emp_field([], 'ssn', 'SSN (9 digits)', 'type="password" maxlength="9" pattern="\d{9}" inputmode="numeric" data-filter="digits" required'); ?>
                <div class="container-login100-form-btn">
                    <button type="submit" class="login100-form-btn">Register</button>
                </div>
                <div class="text-center p-t-90">
                    <a class="txt1" href="employee-register.php?cancel=1">Back to Login</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
