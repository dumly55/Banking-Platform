"""Outgoing email. With no SMTP_HOST configured, messages are appended to storage/logs/mail.log
instead, which is how local development works."""
import smtplib
from datetime import datetime
from email.message import EmailMessage
from email.utils import formataddr
from zoneinfo import ZoneInfo

from flask import current_app

from . import config


def mail_is_logged():
    return not config.MAIL['host']


def send_mail(to, subject, body):
    if mail_is_logged():
        stamp = datetime.now(ZoneInfo(config.TIMEZONE)).isoformat(timespec='seconds')
        with open(config.STORAGE / 'logs' / 'mail.log', 'a', encoding='utf-8') as log:
            log.write(f"[{stamp}] To: {to}\nSubject: {subject}\n\n{body}\n{'-' * 60}\n")
        return True

    mail = config.MAIL
    message = EmailMessage()
    message['From'] = formataddr((mail['from_name'], mail['from']))
    message['To'] = to
    message['Subject'] = subject
    message.set_content(body)
    try:
        if mail['port'] == 465:
            smtp = smtplib.SMTP_SSL(mail['host'], mail['port'], timeout=15)
        else:
            smtp = smtplib.SMTP(mail['host'], mail['port'], timeout=15)
            smtp.starttls()
        with smtp:
            if mail['user']:
                smtp.login(mail['user'], mail['password'])
            smtp.send_message(message)
        return True
    except (smtplib.SMTPException, OSError) as error:
        current_app.logger.error('Mail to %s failed: %s', to, error)
        return False
