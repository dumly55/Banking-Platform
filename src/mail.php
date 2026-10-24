<?php

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

function mail_is_logged(): bool
{
    return !config('mail.host');
}

/**
 * Sends an email. With no SMTP_HOST configured the message is appended to
 * storage/logs/mail.log instead, which is how local development works.
 */
function send_mail(string $to, string $subject, string $body, bool $html = false): bool
{
    $mail = config('mail');

    if (mail_is_logged()) {
        $entry = sprintf("[%s] To: %s\nSubject: %s\n\n%s\n%s\n", date('c'), $to, $subject, $body, str_repeat('-', 60));
        return file_put_contents(config('storage') . '/logs/mail.log', $entry, FILE_APPEND | LOCK_EX) !== false;
    }

    $mailer = new PHPMailer(true);
    try {
        $mailer->isSMTP();
        $mailer->Host       = $mail['host'];
        $mailer->Port       = $mail['port'];
        $mailer->SMTPAuth   = (bool) $mail['user'];
        $mailer->Username   = (string) $mail['user'];
        $mailer->Password   = (string) $mail['pass'];
        $mailer->SMTPSecure = $mail['port'] === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->CharSet    = 'UTF-8';
        $mailer->setFrom($mail['from'], $mail['from_name']);
        $mailer->addAddress($to);
        $mailer->Subject = $subject;
        $mailer->Body    = $body;
        $mailer->isHTML($html);
        return $mailer->send();
    } catch (MailException $e) {
        error_log('Mail to ' . $to . ' failed: ' . $mailer->ErrorInfo);
        return false;
    }
}
