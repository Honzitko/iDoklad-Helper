from unittest.mock import MagicMock

import pytest

import types

import idoklad_helper.pdfco as pdfco


def test_get_credit_balance_success(monkeypatch):
    client = pdfco.PDFCoClient(api_key="key", base_url="https://api.pdf.co")

    response = MagicMock()
    response.status_code = 200
    response.json.return_value = {"Credits": 123.0}

    pdfco.requests = types.SimpleNamespace(get=lambda *args, **kwargs: response)
    pdfco._REQUESTS_AVAILABLE = True

    balance = client.get_credit_balance()
    assert balance == 123.0


def test_get_credit_balance_invalid_response(monkeypatch):
    client = pdfco.PDFCoClient(api_key="key", base_url="https://api.pdf.co")

    response = MagicMock()
    response.status_code = 404
    response.text = "Not found"

    pdfco.requests = types.SimpleNamespace(get=lambda *args, **kwargs: response)
    pdfco._REQUESTS_AVAILABLE = True

    with pytest.raises(RuntimeError):
        client.get_credit_balance()


def test_ai_parser_ping_failure(monkeypatch):
    client = pdfco.AIParsingClient(api_key="abc", endpoint="https://ai")

    response = MagicMock()
    response.status_code = 500
    response.text = "down"

    pdfco.requests = types.SimpleNamespace(get=lambda *args, **kwargs: response)
    pdfco._REQUESTS_AVAILABLE = True

    with pytest.raises(RuntimeError):
        client.ping()


def test_ai_parser_parse_success(monkeypatch):
    client = pdfco.AIParsingClient(api_key="abc", endpoint="https://ai")

    response = MagicMock()
    response.status_code = 200
    response.json.return_value = {"fields": {"total": 10}}

    pdfco.requests = types.SimpleNamespace(
        post=lambda *args, **kwargs: response,
        get=lambda *args, **kwargs: response,
    )
    pdfco._REQUESTS_AVAILABLE = True

    result = client.parse("hello")
    assert result == {"fields": {"total": 10}}
