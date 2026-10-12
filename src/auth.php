<?php
// Session state:
//   $_SESSION['user_id']      logged-in customer
//   $_SESSION['employee_id']  logged-in employee
//   $_SESSION['last_seen']    time of the last request, for the inactivity timeout
//   $_SESSION['mfa']          a pending multi-factor challenge (see mfa.php)

function start_session(): void
{
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

function login_as(string $role, int $id): void
{
    session_regenerate_id(true);
    unset($_SESSION['mfa'], $_SESSION['user_id'], $_SESSION['employee_id']);
    $_SESSION[$role === 'employee' ? 'employee_id' : 'user_id'] = $id;
    $_SESSION['last_seen'] = time();
}

function logout(?string $message = null, string $type = 'info'): void
{
    $_SESSION = [];
    session_destroy();
    start_session();
    session_regenerate_id(true);
    if ($message) flash($message, $type);
}

function current_user_id(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

function current_employee_id(): ?int
{
    return $_SESSION['employee_id'] ?? null;
}

/** Ends the session after SESSION_TIMEOUT seconds without a request. */
function enforce_timeout(string $loginPage): void
{
    if (time() - ($_SESSION['last_seen'] ?? 0) > config('session_timeout')) {
        logout('You were logged out due to inactivity.');
        redirect($loginPage);
    }
    $_SESSION['last_seen'] = time();
}

/** Returns the logged-in customer's id, or redirects to the login page. */
function require_user(): int
{
    if (current_employee_id()) redirect('employee.php');
    if (!current_user_id()) redirect('login.php');
    enforce_timeout('login.php');
    return current_user_id();
}

/**
 * Returns the logged-in employee's row, or redirects. Status and tier are re-read from
 * the database on every request so terminations and tier changes take effect immediately.
 */
function require_employee(int $minTier = 1): array
{
    if (current_user_id()) redirect('dashboard.php');
    if (!current_employee_id()) redirect('employee-login.php');
    enforce_timeout('employee-login.php');

    $employee = db_one('SELECT * FROM employees WHERE id = ?', [current_employee_id()]);
    if (!$employee || $employee['status'] !== 'Hired') {
        logout('Your employee access has been revoked.', 'error');
        redirect('employee-login.php');
    }
    if ($employee['tier'] < $minTier) {
        back_with('employee.php', 'You do not have permission to do that.');
    }
    return $employee;
}

/** Sends already-logged-in visitors to their home page (for login/registration pages). */
function redirect_if_logged_in(): void
{
    if (current_user_id()) redirect('dashboard.php');
    if (current_employee_id()) redirect('employee.php');
}

/** Checks a password against a stored hash, spending the same time when there is no account. */
function password_matches(string $password, ?string $hash): bool
{
    static $dummy = '$2y$10$3bFfIJdY3EBqkbiZtpNaoexw6g4QXN9NKbwNjs9SNWFxI70UalLmu'; // hash of a random string
    return password_verify($password, $hash ?? $dummy) && $hash !== null;
}

// Password rules shared by customer and employee forms: 8+ characters with a letter and a number.
function password_problem(string $password): ?string
{
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Password must be at least 8 characters and contain a letter and a number.';
    }
    return null;
}
