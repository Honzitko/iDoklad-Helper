"""Client helpers for interacting with the PDF.co API."""
from __future__ import annotations

from dataclasses import dataclass
import logging
from typing import Optional

try:  # pragma: no cover - optional dependency guard
    import requests  # type: ignore
    _REQUESTS_AVAILABLE = True
except ModuleNotFoundError:  # pragma: no cover - handled dynamically during tests
    _REQUESTS_AVAILABLE = False

    class _RequestsModule:
        def __getattr__(self, item: str):
            raise ModuleNotFoundError(
                "The 'requests' package is required for PDF.co integration. Install it via 'pip install requests'."
            )

    requests = _RequestsModule()  # type: ignore

logger = logging.getLogger(__name__)


@dataclass(slots=True)
class PDFCoClient:
    """Simple wrapper around the PDF.co REST API."""

    api_key: str
    base_url: str = "https://api.pdf.co"
    timeout_seconds: float = 30.0

    def _headers(self) -> dict[str, str]:
        return {
            "x-api-key": self.api_key,
            "Accept": "application/json",
        }

    def get_credit_balance(self) -> float:
        """Return the remaining credits for the configured account."""

        if not _REQUESTS_AVAILABLE:
            raise ModuleNotFoundError("The 'requests' package is required for PDF.co integration.")
        url = f"{self.base_url.rstrip('/')}/v1/account/credits"
        logger.debug("Requesting PDF.co credit balance", extra={"url": url})
        response = requests.get(url, headers=self._headers(), timeout=self.timeout_seconds)
        if response.status_code != 200:
            message = response.text or response.reason
            logger.error("PDF.co API error", extra={"status": response.status_code, "body": message})
            raise RuntimeError(f"PDF.co API error {response.status_code}: {message}")

        payload = response.json()
        if "Credits" not in payload:
            raise RuntimeError("Unexpected response from PDF.co: 'Credits' field missing")
        return float(payload["Credits"])

    def ensure_ai_parser_allocation(self, parser: "AIParsingClient") -> None:
        """Ping the AI parsing service to ensure it is ready before processing."""

        logger.debug("Validating AI parser readiness")
        parser.ping()


class AIParsingClient:
    """HTTP client for the downstream AI parsing service."""

    def __init__(self, api_key: str, endpoint: str, timeout_seconds: float = 30.0) -> None:
        self.api_key = api_key
        self.endpoint = endpoint.rstrip("/")
        self.timeout_seconds = timeout_seconds

    def ping(self) -> None:
        if not _REQUESTS_AVAILABLE:
            raise ModuleNotFoundError("The 'requests' package is required for the AI parser client.")
        response = requests.get(
            f"{self.endpoint}/status",
            headers={"Authorization": f"Bearer {self.api_key}"},
            timeout=self.timeout_seconds,
        )
        if response.status_code != 200:
            raise RuntimeError(
                f"AI parser status check failed ({response.status_code}): {response.text or response.reason}"
            )

    def parse(self, content: str, metadata: Optional[dict[str, str]] = None) -> dict[str, object]:
        if not _REQUESTS_AVAILABLE:
            raise ModuleNotFoundError("The 'requests' package is required for the AI parser client.")
        payload = {"content": content, "metadata": metadata or {}}
        response = requests.post(
            f"{self.endpoint}/parse",
            json=payload,
            headers={
                "Authorization": f"Bearer {self.api_key}",
                "Accept": "application/json",
            },
            timeout=self.timeout_seconds,
        )
        if response.status_code != 200:
            raise RuntimeError(
                f"AI parser request failed ({response.status_code}): {response.text or response.reason}"
            )
        return response.json()
