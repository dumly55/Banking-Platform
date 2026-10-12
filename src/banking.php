<?php
// Money movement. Every balance change runs inside a database transaction with the affected
// account rows locked (SELECT ... FOR UPDATE), so concurrent requests can't double-spend.

const ACCOUNT_TYPES = [
    // type => [card number prefix, annual interest rate]
    'Checking' => ['4100', '0.0005'],
    'Savings'  => ['4200', '0.0057'],
];

const DEPOSIT_IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
const DEPOSIT_MAX_BYTES = 5 * 1024 * 1024;

function user_accounts(int $userId): array
{
    return db_all('SELECT * FROM accounts WHERE user_id = ? ORDER BY created_at, account_number', [$userId]);
}

function user_account(int $userId, string $accountNumber): ?array
{
    return db_one('SELECT * FROM accounts WHERE account_number = ? AND user_id = ?', [$accountNumber, $userId]);
}

function open_account(int $userId, string $type): string
{
    [$prefix, $rate] = ACCOUNT_TYPES[$type];
    $number = unique_number('accounts', 'account_number', 16, $prefix);
    db_exec(
        'INSERT INTO accounts (account_number, user_id, type, interest_rate, interest_applied_on) VALUES (?, ?, ?, ?, CURDATE())',
        [$number, $userId, $type, $rate]
    );
    return $number;
}

function record_transaction(string $account, string $type, string $status, string $amount, ?string $before, string $description, ?string $related = null, ?string $id = null): string
{
    $id ??= unique_number('transactions', 'id', 12);
    db_exec(
        'INSERT INTO transactions (id, account_number, type, status, amount, balance_before, description, related_transaction) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$id, $account, $type, $status, $amount, $before, $description, $related]
    );
    return $id;
}

/** Pays yearly compound interest on every anniversary that has passed. Pass a user id to limit it to their accounts. */
function apply_interest(?int $userId = null): void
{
    $where = 'interest_applied_on <= CURDATE() - INTERVAL 1 YEAR' . ($userId ? ' AND user_id = ?' : '');
    $due = db_all("SELECT account_number FROM accounts WHERE $where", $userId ? [$userId] : []);

    foreach ($due as ['account_number' => $number]) {
        db_transaction(function () use ($number) {
            $acc = db_one(
                'SELECT balance, interest_rate, TIMESTAMPDIFF(YEAR, interest_applied_on, CURDATE()) AS years
                 FROM accounts WHERE account_number = ? FOR UPDATE',
                [$number]
            );
            if ($acc['years'] < 1) return;
            $newBalance = round($acc['balance'] * (1 + $acc['interest_rate']) ** $acc['years'], 2);
            db_exec(
                'UPDATE accounts SET balance = ?, interest_applied_on = interest_applied_on + INTERVAL ? YEAR WHERE account_number = ?',
                [$newBalance, $acc['years'], $number]
            );
            $interest = round($newBalance - $acc['balance'], 2);
            if ($interest > 0) {
                record_transaction($number, 'Interest', 'Processed', (string) $interest, $acc['balance'], 'Interest payment');
            }
        });
    }
}

/** Locks the given accounts in a fixed order (avoids deadlocks) and returns them keyed by number. */
function lock_accounts(string ...$numbers): array
{
    sort($numbers);
    $placeholders = implode(',', array_fill(0, count($numbers), '?'));
    $rows = db_all("SELECT * FROM accounts WHERE account_number IN ($placeholders) ORDER BY account_number FOR UPDATE", $numbers);
    return array_column($rows, null, 'account_number');
}

/** Returns an error message, or null when the transfer is valid. Used before and after MFA. */
function transfer_problem(int $userId, string $from, string $to, ?string $amount): ?string
{
    if ($amount === null) return 'Enter a valid amount greater than $0.00.';
    if ($from === $to) return 'You cannot transfer money to and from the same account.';
    if (!user_account($userId, $from)) return 'Choose one of your own accounts to transfer from.';
    if (!db_value('SELECT 1 FROM accounts WHERE account_number = ?', [$to])) return 'Recipient account does not exist.';
    return null;
}

function is_own_account(int $userId, string $accountNumber): bool
{
    return user_account($userId, $accountNumber) !== null;
}

/** Moves money between accounts. Returns an error message, or null on success. */
function transfer(int $userId, string $from, string $to, string $amount): ?string
{
    return db_transaction(function () use ($userId, $from, $to, $amount) {
        $accounts = lock_accounts($from, $to);
        $sender = $accounts[$from] ?? null;
        $recipient = $accounts[$to] ?? null;

        if (!$sender || (int) $sender['user_id'] !== $userId || !$recipient || $from === $to) {
            return 'That transfer is no longer valid.';
        }
        if ((float) $sender['balance'] < (float) $amount) {
            return 'Insufficient funds: your balance is ' . money($sender['balance']) . '.';
        }

        db_exec('UPDATE accounts SET balance = balance - ? WHERE account_number = ?', [$amount, $from]);
        db_exec('UPDATE accounts SET balance = balance + ? WHERE account_number = ?', [$amount, $to]);

        $outId = unique_number('transactions', 'id', 12);
        $inId = unique_number('transactions', 'id', 12);
        // Statements show only the last 4 digits of the other account, like a real bank.
        record_transaction($from, 'Transfer Out', 'Processed', "-$amount", $sender['balance'], 'Transfer to account ending ' . substr($to, -4), $inId, $outId);
        record_transaction($to, 'Transfer In', 'Processed', $amount, $recipient['balance'], 'Transfer from account ending ' . substr($from, -4), $outId, $inId);
        return null;
    });
}

/** ATM withdrawal. Returns an error message, or null on success. */
function withdraw(int $userId, string $accountNumber, string $amount): ?string
{
    return db_transaction(function () use ($userId, $accountNumber, $amount) {
        $account = lock_accounts($accountNumber)[$accountNumber] ?? null;
        if (!$account || (int) $account['user_id'] !== $userId) return 'Account not found.';
        if ((float) $account['balance'] < (float) $amount) {
            return 'Insufficient funds: your balance is ' . money($account['balance']) . '.';
        }
        db_exec('UPDATE accounts SET balance = balance - ? WHERE account_number = ?', [$amount, $accountNumber]);
        record_transaction($accountNumber, 'Withdrawal', 'Processed', "-$amount", $account['balance'], 'ATM withdrawal');
        return null;
    });
}

/** Validates an uploaded check image and stores it under a random name. Returns [file name, error]. */
function store_check_image(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [null, 'Please upload both sides of the check.'];
    if ($file['error'] !== UPLOAD_ERR_OK) return [null, 'The upload failed. Please try again.'];
    if ($file['size'] > DEPOSIT_MAX_BYTES) return [null, 'Check images must be 5 MB or smaller.'];

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext = DEPOSIT_IMAGE_TYPES[$mime] ?? null;
    if (!$ext) return [null, 'Check images must be JPG, PNG, GIF or WebP files.'];

    $name = bin2hex(random_bytes(16)) . ".$ext";
    if (!move_uploaded_file($file['tmp_name'], config('storage') . "/uploads/deposits/$name")) {
        return [null, 'Could not save the uploaded image.'];
    }
    return [$name, null];
}

/** Creates a pending check deposit for an employee to review. Returns an error message, or null. */
function create_deposit(int $userId, string $accountNumber, string $amount, array $front, array $back): ?string
{
    if (!user_account($userId, $accountNumber)) return 'Choose one of your accounts to deposit into.';

    [$frontName, $error] = store_check_image($front);
    if ($error) return $error;
    [$backName, $error] = store_check_image($back);
    if ($error) {
        @unlink(config('storage') . "/uploads/deposits/$frontName");
        return $error;
    }

    db_transaction(function () use ($accountNumber, $amount, $frontName, $backName) {
        $id = record_transaction($accountNumber, 'Deposit', 'Pending', $amount, null, 'Mobile check deposit');
        db_exec('INSERT INTO check_deposits (transaction_id, front_image, back_image) VALUES (?, ?, ?)', [$id, $frontName, $backName]);
    });
    return null;
}

/** Approves or denies a pending check deposit. Returns false if it was already reviewed. */
function review_deposit(int $employeeId, string $transactionId, bool $approve): bool
{
    return db_transaction(function () use ($employeeId, $transactionId, $approve) {
        $tx = db_one(
            "SELECT * FROM transactions WHERE id = ? AND type = 'Deposit' AND status = 'Pending' FOR UPDATE",
            [$transactionId]
        );
        if (!$tx) return false;

        $account = lock_accounts($tx['account_number'])[$tx['account_number']];
        if ($approve) {
            db_exec('UPDATE accounts SET balance = balance + ? WHERE account_number = ?', [$tx['amount'], $tx['account_number']]);
        }
        db_exec(
            'UPDATE transactions SET status = ?, balance_before = ? WHERE id = ?',
            [$approve ? 'Processed' : 'Denied', $account['balance'], $transactionId]
        );
        db_exec('UPDATE check_deposits SET reviewed_by = ?, reviewed_at = NOW() WHERE transaction_id = ?', [$employeeId, $transactionId]);
        return true;
    });
}
