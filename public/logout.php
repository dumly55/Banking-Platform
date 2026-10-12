<?php
require __DIR__ . '/../src/bootstrap.php';

$wasEmployee = current_employee_id() || ($_SESSION['mfa']['purpose'] ?? '') === 'employee_login';
$wasLoggedIn = current_employee_id() || current_user_id();
logout($wasLoggedIn ? 'You have been logged out.' : null);
redirect($wasEmployee ? 'employee-login.php' : ($wasLoggedIn ? 'login.php' : 'index.html'));
