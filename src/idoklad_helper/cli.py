"""Command line entry point for iDoklad helper tasks."""
from __future__ import annotations

import json
import logging
from pathlib import Path
from typing import Optional

import typer

from .config import Settings, load_settings
from .email_checker import EmailChecker
from .pdfco import AIParsingClient, PDFCoClient
from .queue_processor import QueueProcessor

app = typer.Typer(help="Automation utilities for the iDoklad workflow")
logging.basicConfig(level=logging.INFO, format="%(levelname)s %(name)s: %(message)s")


def _build_settings(config_path: Optional[Path]) -> Settings:
    return load_settings(config_path)


def _build_queue_processor(settings: Settings) -> QueueProcessor:
    pdfco_client = PDFCoClient(api_key=settings.pdfco.api_key, base_url=settings.pdfco.base_url)
    ai_client = AIParsingClient(
        api_key=settings.ai_parser.api_key,
        endpoint=settings.ai_parser.endpoint,
        timeout_seconds=settings.ai_parser.timeout_seconds,
    )
    return QueueProcessor(
        database_path=settings.queue.database_path,
        pdfco_client=pdfco_client,
        ai_client=ai_client,
    )


@app.command("pdfco-balance")
def pdfco_balance(config: Optional[Path] = typer.Option(None, "--config", help="Path to config file")) -> None:
    """Display the remaining PDF.co credits."""

    settings = _build_settings(config)
    client = PDFCoClient(api_key=settings.pdfco.api_key, base_url=settings.pdfco.base_url)
    balance = client.get_credit_balance()
    typer.echo(f"PDF.co credits remaining: {balance}")


@app.command("check-emails")
def check_emails(config: Optional[Path] = typer.Option(None, "--config", help="Path to config file")) -> None:
    """Fetch unseen e-mails and add them to the processing queue."""

    settings = _build_settings(config)
    checker = EmailChecker(
        host=settings.email.imap_host,
        port=settings.email.imap_port,
        username=settings.email.username,
        password=settings.email.password,
        mailbox=settings.email.mailbox,
    )
    messages = checker.fetch_unseen()
    queue = _build_queue_processor(settings)
    count = queue.enqueue_messages(messages)
    typer.echo(f"Queued {count} new messages")


@app.command("process-queue")
def process_queue(config: Optional[Path] = typer.Option(None, "--config", help="Path to config file")) -> None:
    """Process pending queue items using the AI parser."""

    settings = _build_settings(config)
    queue = _build_queue_processor(settings)
    processed = queue.process_pending()
    typer.echo(json.dumps({"processed": processed}))


if __name__ == "__main__":
    app()
