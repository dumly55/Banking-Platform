<?php
// Settings come from environment variables, optionally loaded from a .env file in the project root.

$envFile = dirname(__DIR__) . '/.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = preg_match('/^(["\'])(.*)\1/', $value, $m) ? $m[2] : trim(preg_replace('/\s+#.*$/', '', $value));
        if (getenv($key) === false) putenv("$key=$value");
    }
}

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value === false || $value === '' ? $default : $value;
}

return [
    // Defaults to production so a server without a .env never shows errors or on-screen MFA codes.
    'env'      => env('APP_ENV', 'production'),
    'url'      => rtrim(env('APP_URL', 'http://localhost:8000'), '/'),
    'timezone' => env('APP_TIMEZONE', 'America/Los_Angeles'),
    // Secret used to fingerprint SSNs. database/setup.php generates it; changing it breaks duplicate checks.
    'key'      => env('APP_KEY'),
    'session_timeout' => (int) env('SESSION_TIMEOUT', '600'),

    'db' => [
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'name' => env('DB_NAME', 'bank_of_the_future'),
        'user' => env('DB_USER', 'root'),
        'pass' => env('DB_PASS', ''),
    ],

    // Leave SMTP_HOST empty to write outgoing mail to storage/logs/mail.log instead of sending it.
    'mail' => [
        'host'      => env('SMTP_HOST'),
        'port'      => (int) env('SMTP_PORT', '587'),
        'user'      => env('SMTP_USER'),
        'pass'      => env('SMTP_PASS'),
        'from'      => env('MAIL_FROM', 'no-reply@bankofthefuture.test'),
        'from_name' => env('MAIL_FROM_NAME', 'Bank of the Future'),
    ],

    'storage' => dirname(__DIR__) . '/storage',
];
