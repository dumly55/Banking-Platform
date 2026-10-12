<?php
// Server-side validation for the personal details collected from customers and employees.

const PERSON_FIELDS = ['first', 'last', 'ssn', 'phone', 'lstate', 'license', 'address', 'address2', 'city', 'state', 'zip'];

const PERSON_RULES = [
    'first'    => ['/^.{1,50}$/u', 'Enter your first name.'],
    'last'     => ['/^.{1,50}$/u', 'Enter your last name.'],
    'ssn'      => ['/^\d{9}$/', 'Social Security Number must be 9 digits.'],
    'phone'    => ['/^\d{10}$/', 'Phone number must be 10 digits.'],
    'lstate'   => ['/^[A-Z]{2}$/', 'License state must be a 2-letter code.'],
    'license'  => ['/^[A-Za-z0-9]{1,20}$/', 'License number must be 1-20 letters or numbers.'],
    'address'  => ['/^.{1,100}$/u', 'Enter your street address.'],
    'address2' => ['/^.{0,100}$/u', 'Address line 2 is too long.'],
    'city'     => ['/^[A-Za-z ,.\'-]{1,60}$/', 'Enter a valid city.'],
    'state'    => ['/^[A-Z]{2}$/', 'State must be a 2-letter code.'],
    'zip'      => ['/^\d{5}$/', 'Zip code must be 5 digits.'],
];

/**
 * SSNs are only needed to stop duplicate sign-ups, so only a keyed hash is stored.
 * Without the key, the 1-billion possible SSNs can't be brute-forced back from it.
 */
function ssn_fingerprint(string $ssn): string
{
    $key = config('key');
    if (!$key) throw new RuntimeException('APP_KEY is not set. Run: php database/setup.php');
    return hash_hmac('sha256', $ssn, $key);
}

/** Reads the given fields from POST, trimmed. */
function collect_input(array $fields): array
{
    $values = [];
    foreach ($fields as $field) $values[$field] = input($field);
    return $values;
}

/** Returns error messages for any person field in $values that breaks PERSON_RULES. */
function person_errors(array $values): array
{
    $errors = [];
    foreach (PERSON_RULES as $field => [$pattern, $message]) {
        if (!preg_match($pattern, $values[$field] ?? '')) $errors[] = $message;
    }
    return $errors;
}

/** Validates password + confirmation from POST. */
function password_errors(): array
{
    $password = $_POST['password'] ?? '';
    $errors = [];
    if ($problem = password_problem($password)) $errors[] = $problem;
    if ($password !== ($_POST['cpassword'] ?? '')) $errors[] = 'Passwords do not match.';
    return $errors;
}

/** Renders a floating-label input for the auth.css form style. */
function floating_field(array $values, string $name, string $label, string $attrs = ''): void
{
    $value = e($values[$name] ?? '');
    echo "<div class=\"input-box\">
            <input placeholder=\"\" id=\"$name\" name=\"$name\" value=\"$value\" $attrs>
            <span class=\"floating-label\">$label</span>
          </div>";
}
