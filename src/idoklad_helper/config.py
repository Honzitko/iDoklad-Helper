"""Configuration loading for the iDoklad helper tools."""
from __future__ import annotations

from dataclasses import dataclass
import os
from pathlib import Path
from typing import Any, Dict, Optional

try:  # Python 3.11+
    import tomllib  # type: ignore[attr-defined]
except ModuleNotFoundError:  # pragma: no cover - fallback for very old runtimes
    import tomli as tomllib  # type: ignore[no-redef]


CONFIG_PATH = Path("config.toml")


@dataclass(slots=True)
class PDFCoSettings:
    api_key: str
    base_url: str = "https://api.pdf.co"


@dataclass(slots=True)
class EmailSettings:
    imap_host: str
    imap_port: int = 993
    username: str = ""
    password: str = ""
    mailbox: str = "INBOX"


@dataclass(slots=True)
class AIParserSettings:
    api_key: str
    endpoint: str
    timeout_seconds: float = 30.0


@dataclass(slots=True)
class QueueSettings:
    database_path: Path = Path("data/queue.db")


@dataclass(slots=True)
class Settings:
    pdfco: PDFCoSettings
    email: EmailSettings
    ai_parser: AIParserSettings
    queue: QueueSettings


def load_settings(path: Optional[Path] = None) -> Settings:
    """Load application settings from a TOML file and environment variables."""

    path = path or CONFIG_PATH
    raw: Dict[str, Dict[str, Any]] = {
        "pdfco": {
            "api_key": os.environ.get("PDFCO_API_KEY", ""),
            "base_url": os.environ.get("PDFCO_BASE_URL", "https://api.pdf.co"),
        },
        "email": {
            "imap_host": os.environ.get("IMAP_HOST", ""),
            "imap_port": int(os.environ.get("IMAP_PORT", "993")),
            "username": os.environ.get("IMAP_USERNAME", ""),
            "password": os.environ.get("IMAP_PASSWORD", ""),
            "mailbox": os.environ.get("IMAP_MAILBOX", "INBOX"),
        },
        "ai_parser": {
            "api_key": os.environ.get("AI_PARSER_API_KEY", ""),
            "endpoint": os.environ.get("AI_PARSER_ENDPOINT", ""),
            "timeout_seconds": float(os.environ.get("AI_PARSER_TIMEOUT", "30")),
        },
        "queue": {
            "database_path": Path(os.environ.get("QUEUE_DATABASE_PATH", "data/queue.db")),
        },
    }

    if path.exists():
        with path.open("rb") as fp:
            data = tomllib.load(fp)
        for section, values in data.items():
            if section in raw:
                raw_section = raw[section]
                for key, value in values.items():
                    if key == "database_path":
                        raw_section[key] = Path(value)
                    else:
                        raw_section[key] = value

    pdfco = PDFCoSettings(**raw["pdfco"])
    email = EmailSettings(**raw["email"])
    ai_parser = AIParserSettings(**raw["ai_parser"])
    queue = QueueSettings(**raw["queue"])
    return Settings(pdfco=pdfco, email=email, ai_parser=ai_parser, queue=queue)
