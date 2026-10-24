<?php
require __DIR__ . '/../src/bootstrap.php';
$userId = require_user();
if (!is_post()) redirect('dashboard.php');
verify_csrf();

$type = input('type');
if (!isset(ACCOUNT_TYPES[$type])) back_with('dashboard.php', 'Choose an account type.');

$number = open_account($userId, $type);
back_with('dashboard.php', "Your new $type account (#$number) is open.", 'success');
