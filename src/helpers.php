<?php

function config(string $key): mixed
{
    $value = $GLOBALS['config'];
    foreach (explode('.', $key) as $part) $value = $value[$part];
    return $value;
}

/** Escapes a value for HTML output. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never
{
    header("Location: $url");
    exit;
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function input(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

// ---- Flash messages: shown once on the next page load ----

function flash(string $message, string $type = 'error'): void
{
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}

function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/** Sets a flash message and redirects; the usual way to end a POST handler. */
function back_with(string $url, string $message, string $type = 'error'): never
{
    flash($message, $type);
    redirect($url);
}

// ---- CSRF protection for every form that changes state ----

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function verify_csrf(): void
{
    if (!hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('Your session expired. Please go back and try again.');
    }
}

// ---- Money ----

/** Parses a positive dollar amount with at most 2 decimals, or returns null. */
function parse_amount(string $raw): ?string
{
    if (!preg_match('/^\d{1,9}(\.\d{1,2})?$/', $raw) || (float) $raw <= 0) return null;
    return number_format((float) $raw, 2, '.', '');
}

function money(mixed $amount): string
{
    $amount = (float) $amount;
    return ($amount < 0 ? '-' : '') . '$' . number_format(abs($amount), 2);
}

function format_card_number(string $number): string
{
    return trim(chunk_split($number, 4, ' '));
}

function view(string $name, array $vars = []): void
{
    extract($vars);
    require __DIR__ . "/views/$name.php";
}
