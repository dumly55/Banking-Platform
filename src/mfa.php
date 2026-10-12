<?php
// Email-based one-time codes. A challenge is started for a purpose (logging in, an external
// transfer, or a password reset) and the action runs only after the code is verified on mfa.php.

const MFA_TTL = 600;         // seconds a code stays valid
const MFA_MAX_ATTEMPTS = 5;  // wrong guesses before the challenge is discarded
const MFA_MAX_SENDS = 4;     // first code plus 3 resends

/**
 * @param string $purpose  user_login | employee_login | transfer | reset_user | reset_employee
 * @param ?string $email   null starts a decoy challenge that sends nothing and can never pass,
 *                         so forms like password reset look the same for unknown emails
 * @param array  $data     whatever the purpose needs once verified (ids, transfer details...)
 */
function mfa_start(string $purpose, ?string $email, array $data): void
{
    $_SESSION['mfa'] = ['purpose' => $purpose, 'email' => $email, 'data' => $data];
    mfa_send_code();
}

function mfa_pending(): ?array
{
    return $_SESSION['mfa'] ?? null;
}

/** Emails a fresh code. Returns false once the resend limit is reached. */
function mfa_send_code(): bool
{
    $sends = $_SESSION['mfa']['sends'] ?? 0;
    if ($sends >= MFA_MAX_SENDS) return false;
    $_SESSION['mfa']['sends'] = $sends + 1;

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['mfa']['hash']     = password_hash($code, PASSWORD_DEFAULT);
    $_SESSION['mfa']['expires']  = time() + MFA_TTL;
    $_SESSION['mfa']['attempts'] = 0;

    $email = $_SESSION['mfa']['email'];
    if ($email === null) return true; // decoy: nothing to send

    // Without real email, show the code on the MFA page so the app is usable locally.
    if (mail_is_logged() && config('env') !== 'production') $_SESSION['mfa']['dev_code'] = $code;

    send_mail(
        $email,
        'Your Bank of the Future verification code',
        "Your verification code is: $code\n\nIt expires in " . (MFA_TTL / 60) . " minutes. If you did not request it, you can ignore this email."
    );
    return true;
}

/** Returns the pending challenge if $code is correct; otherwise null (and counts the attempt). */
function mfa_verify(string $code): ?array
{
    $mfa = mfa_pending();
    if (!$mfa) return null;

    if (time() > $mfa['expires'] || $mfa['attempts'] >= MFA_MAX_ATTEMPTS) {
        unset($_SESSION['mfa']);
        return null;
    }
    if (!preg_match('/^\d{6}$/', $code) || !password_verify($code, $mfa['hash'])) {
        $_SESSION['mfa']['attempts']++;
        return null;
    }
    unset($_SESSION['mfa']);
    return $mfa;
}
