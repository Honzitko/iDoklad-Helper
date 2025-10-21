"""Utilities to fetch e-mails from an IMAP inbox."""
from __future__ import annotations

import email
from email.message import EmailMessage
import imaplib
import logging
from dataclasses import dataclass
from typing import List

logger = logging.getLogger(__name__)


@dataclass(slots=True)
class EmailChecker:
    host: str
    port: int
    username: str
    password: str
    mailbox: str = "INBOX"
    use_ssl: bool = True

    def _connect(self) -> imaplib.IMAP4:
        logger.debug("Connecting to IMAP server", extra={"host": self.host, "port": self.port})
        if self.use_ssl:
            return imaplib.IMAP4_SSL(self.host, self.port)
        return imaplib.IMAP4(self.host, self.port)

    def fetch_unseen(self) -> List[EmailMessage]:
        """Fetch unseen e-mails and mark them as seen."""

        with self._connect() as conn:
            conn.login(self.username, self.password)
            conn.select(self.mailbox)
            status, data = conn.search(None, "UNSEEN")
            if status != "OK":
                raise RuntimeError("Unable to search mailbox for unseen messages")

            emails: List[EmailMessage] = []
            for num in data[0].split():
                status, msg_data = conn.fetch(num, "(RFC822)")
                if status != "OK" or not msg_data:
                    logger.warning("Failed to fetch message", extra={"msg_id": num})
                    continue
                message = email.message_from_bytes(msg_data[0][1])
                if not isinstance(message, EmailMessage):
                    message = EmailMessage()
                    message.set_content("")
                emails.append(message)
                conn.store(num, "+FLAGS", "(\\Seen)")
            logger.info("Fetched %s unseen e-mails", len(emails))
            return emails


def extract_plain_text(message: EmailMessage) -> str:
    """Extract the plain text payload from a message."""

    if message.is_multipart():
        for part in message.walk():
            if part.get_content_type() == "text/plain":
                payload = part.get_payload(decode=True)
                if payload is not None:
                    return payload.decode(part.get_content_charset() or "utf-8", errors="replace")
    else:
        payload = message.get_payload(decode=True)
        if payload is not None:
            return payload.decode(message.get_content_charset() or "utf-8", errors="replace")
    return ""
