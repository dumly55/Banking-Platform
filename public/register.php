<?php
require __DIR__ . '/../src/bootstrap.php';
// Customers register themselves; employees can also open this page to register a customer.
if (current_user_id()) redirect('dashboard.php');
$byEmployee = (bool) current_employee_id();
if ($byEmployee) require_employee();

$old = [];
$errors = [];

if (is_post()) {
    verify_csrf();
    $old = collect_input([...PERSON_FIELDS, 'username', 'email']);
    $password = $_POST['password'] ?? '';
    $pin = $_POST['pin'] ?? '';

    $errors = [...person_errors($old), ...password_errors()];
    if (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $old['username'])) $errors[] = 'Username must be 3-30 letters, numbers, dots or underscores.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (!preg_match('/^\d{4}$/', $pin)) $errors[] = 'PIN must be 4 digits.';
    if ($pin !== ($_POST['cpin'] ?? '')) $errors[] = 'PINs do not match.';

    if (!$errors) {
        // One query for every uniqueness check instead of five.
        $taken = db_one(
            'SELECT
                MAX(username = ?) AS username, MAX(email = ?) AS email, MAX(phone = ?) AS phone,
                MAX(ssn = ? OR (license_state = ? AND license_number = ?)) AS identity
             FROM users WHERE username = ? OR email = ? OR phone = ? OR ssn = ? OR (license_state = ? AND license_number = ?)',
            array_merge(
                [$old['username'], $old['email'], $old['phone'], $old['ssn'], $old['lstate'], $old['license']],
                [$old['username'], $old['email'], $old['phone'], $old['ssn'], $old['lstate'], $old['license']]
            )
        );
        if ($taken['username']) $errors[] = 'That username is already taken.';
        if ($taken['email']) $errors[] = 'That email is already registered.';
        if ($taken['phone']) $errors[] = 'That phone number is already registered.';
        if ($taken['identity']) $errors[] = 'Registration unsuccessful. Please contact support.';
    }

    if (!$errors) {
        try {
            db_exec(
                'INSERT INTO users (username, password_hash, pin_hash, first_name, last_name, email, phone, ssn,
                                    license_state, license_number, address1, address2, city, state, zip)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $old['username'], password_hash($password, PASSWORD_DEFAULT), password_hash($pin, PASSWORD_DEFAULT),
                    $old['first'], $old['last'], $old['email'], $old['phone'], $old['ssn'],
                    $old['lstate'], $old['license'], $old['address'], $old['address2'] ?: null,
                    $old['city'], $old['state'], $old['zip'],
                ]
            );
            if ($byEmployee) back_with('employee.php', "Customer {$old['username']} registered.", 'success');
            back_with('login.php', 'Registration successful. You can now log in.', 'success');
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') throw $e;
            $errors[] = 'Some of those details are already registered.'; // lost a race with another signup
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Bank of the Future</title>
    <link rel="icon" href="assets/img/favicon.ico">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="assets/css/common.css">
    <script src="assets/js/forms.js" defer></script>
</head>
<body>
    <?php view('flash'); ?>
    <div class="wrapper">
        <form action="register.php" id="register" method="post">
            <?= csrf_field() ?>
            <h1>Register</h1>
            <?php if ($errors): ?>
                <ul class="form-errors">
                    <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php floating_field($old, 'first', 'First Name', 'type="text" maxlength="50" required autocomplete="given-name"'); ?>
            <?php floating_field($old, 'last', 'Last Name', 'type="text" maxlength="50" required autocomplete="family-name"'); ?>
            <?php floating_field($old, 'username', 'Username', 'type="text" maxlength="30" pattern="[A-Za-z0-9_.]{3,30}" required autocomplete="username"'); ?>
            <div class="input-box">
                <input type="password" placeholder="" id="password" name="password" minlength="8" pattern="(?=.*[A-Za-z])(?=.*\d).{8,}" required autocomplete="new-password">
                <span class="floating-label">Password (minimum 8 characters, 1 letter, &amp; 1 number)</span>
            </div>
            <div class="input-box">
                <input type="password" placeholder="" id="cpassword" name="cpassword" data-match="#password" required autocomplete="new-password">
                <span class="floating-label">Confirm Password</span>
                <span class="match-message"></span>
            </div>
            <div class="input-box">
                <input type="password" placeholder="" id="pin" name="pin" maxlength="4" pattern="\d{4}" inputmode="numeric" data-filter="digits" required>
                <span class="floating-label">4-Digit PIN</span>
            </div>
            <div class="input-box">
                <input type="password" placeholder="" id="cpin" name="cpin" maxlength="4" inputmode="numeric" data-filter="digits" data-match="#pin" required>
                <span class="floating-label">Confirm 4-Digit PIN</span>
                <span class="match-message"></span>
            </div>
            <?php floating_field($old, 'email', 'Email', 'type="email" maxlength="254" required autocomplete="email"'); ?>
            <?php floating_field([], 'ssn', 'Social Security Number (9 digits)', 'type="password" maxlength="9" pattern="\d{9}" inputmode="numeric" data-filter="digits" required'); ?>
            <?php floating_field($old, 'phone', 'Phone Number (10 digits)', 'type="text" maxlength="10" pattern="\d{10}" inputmode="numeric" data-filter="digits" required autocomplete="tel-national"'); ?>
            <?php floating_field($old, 'lstate', 'License State (ex: CA, NY, NC)', 'type="text" maxlength="2" pattern="[A-Z]{2}" data-filter="upper" required'); ?>
            <?php floating_field($old, 'license', 'License Number', 'type="text" maxlength="20" required'); ?>
            <?php floating_field($old, 'address', 'Address Line 1', 'type="text" maxlength="100" required autocomplete="address-line1"'); ?>
            <?php floating_field($old, 'address2', 'Address Line 2 (Optional)', 'type="text" maxlength="100" autocomplete="address-line2"'); ?>
            <?php floating_field($old, 'city', 'City', 'type="text" maxlength="60" data-filter="letters" required autocomplete="address-level2"'); ?>
            <?php floating_field($old, 'state', 'State (ex: CA, NY, NC)', 'type="text" maxlength="2" pattern="[A-Z]{2}" data-filter="upper" required'); ?>
            <?php floating_field($old, 'zip', 'Zip Code', 'type="text" maxlength="5" pattern="\d{5}" inputmode="numeric" data-filter="digits" required autocomplete="postal-code"'); ?>

            <button type="submit" class="btn">Register</button>
            <div class="register-link">
                <?php if ($byEmployee): ?>
                    <p><a href="employee.php">Back to employee dashboard</a></p>
                <?php else: ?>
                    <p>Already have an account? <a href="login.php">Login</a></p>
                <?php endif; ?>
            </div>
        </form>
    </div>
</body>
</html>
