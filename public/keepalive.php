<?php
// Pinged by session-timeout.js while the user is active, to refresh the server-side inactivity timer.
require __DIR__ . '/../src/bootstrap.php';

current_employee_id() ? require_employee() : require_user();
http_response_code(204);
