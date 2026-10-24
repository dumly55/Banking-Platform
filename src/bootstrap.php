<?php
// Included at the top of every page in public/.

$GLOBALS['config'] = require __DIR__ . '/config.php';

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/mail.php';
require __DIR__ . '/mfa.php';
require __DIR__ . '/banking.php';
require __DIR__ . '/validation.php';

date_default_timezone_set(config('timezone'));

// Never print function arguments (e.g. the DB password) in error traces.
ini_set('zend.exception_ignore_args', '1');

if (config('env') === 'production') {
    ini_set('display_errors', '0');
}

if (PHP_SAPI !== 'cli') {
    start_session();
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}
