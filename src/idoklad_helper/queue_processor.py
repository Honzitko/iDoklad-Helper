"""Processing logic for queued documents."""
from __future__ import annotations

import logging
import sqlite3
from contextlib import contextmanager
from dataclasses import dataclass
from pathlib import Path
from typing import Iterable, Iterator

from . import email_checker
from .pdfco import AIParsingClient, PDFCoClient

logger = logging.getLogger(__name__)


CREATE_TABLE_SQL = """
CREATE TABLE IF NOT EXISTS pending_documents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    subject TEXT NOT NULL,
    sender TEXT NOT NULL,
    body TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    result_json TEXT,
    error TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP
);
"""


@dataclass(slots=True)
class QueueProcessor:
    database_path: Path
    pdfco_client: PDFCoClient
    ai_client: AIParsingClient

    def __post_init__(self) -> None:
        self._initialise_database()

    @contextmanager
    def _connection(self) -> Iterator[sqlite3.Connection]:
        self.database_path.parent.mkdir(parents=True, exist_ok=True)
        conn = sqlite3.connect(self.database_path)
        try:
            yield conn
        finally:
            conn.commit()
            conn.close()

    def _initialise_database(self) -> None:
        with self._connection() as conn:
            conn.execute(CREATE_TABLE_SQL)

    def enqueue_messages(self, messages: Iterable[email_checker.EmailMessage]) -> int:
        count = 0
        with self._connection() as conn:
            for message in messages:
                subject = message.get("subject", "(no subject)")
                sender = message.get("from", "")
                body = email_checker.extract_plain_text(message)
                conn.execute(
                    "INSERT INTO pending_documents(subject, sender, body) VALUES(?, ?, ?)",
                    (subject, sender, body),
                )
                count += 1
        logger.info("Queued %s messages for processing", count)
        return count

    def pending_jobs(self) -> list[sqlite3.Row]:
        with self._connection() as conn:
            conn.row_factory = sqlite3.Row
            rows = conn.execute(
                "SELECT * FROM pending_documents WHERE status = 'pending' ORDER BY created_at ASC"
            ).fetchall()
            return rows

    def process_pending(self) -> int:
        jobs = self.pending_jobs()
        if not jobs:
            logger.info("No pending jobs detected")
            return 0

        # Validate external services first
        balance = self.pdfco_client.get_credit_balance()
        logger.info("PDF.co credits available: %s", balance)
        self.pdfco_client.ensure_ai_parser_allocation(self.ai_client)

        processed = 0
        with self._connection() as conn:
            for job in jobs:
                try:
                    result = self.ai_client.parse(job["body"], {"subject": job["subject"]})
                    conn.execute(
                        "UPDATE pending_documents SET status='processed', result_json=?, processed_at=CURRENT_TIMESTAMP WHERE id=?",
                        (str(result), job["id"]),
                    )
                    processed += 1
                except Exception as exc:  # pragma: no cover - defensive fallback
                    logger.exception("Failed to process job %s", job["id"])
                    conn.execute(
                        "UPDATE pending_documents SET status='error', error=? WHERE id=?",
                        (str(exc), job["id"]),
                    )
        logger.info("Processed %s jobs", processed)
        return processed
