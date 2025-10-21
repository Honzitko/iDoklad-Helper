# iDoklad Helper

This repository provides a small automation toolkit to:

* Verify PDF.co API credentials and retrieve remaining credits.
* Fetch e-mails from an IMAP inbox and queue them for later processing.
* Process queued documents through an AI parsing service.

The code has been restructured to ensure each workflow succeeds without the
runtime errors previously observed.

## Configuration

Configuration values can be defined in `config.toml` (see
`config.example.toml`) or provided through environment variables. Environment
variables take precedence over values stored in `config.toml`.

### Required settings

| Setting | Environment Variable | Description |
| ------- | -------------------- | ----------- |
| `pdfco.api_key` | `PDFCO_API_KEY` | API key for PDF.co requests. |
| `pdfco.base_url` | `PDFCO_BASE_URL` | Override API hostname (defaults to `https://api.pdf.co`). |
| `email.imap_host` | `IMAP_HOST` | Hostname of the IMAP server. |
| `email.imap_port` | `IMAP_PORT` | IMAP port, defaults to `993`. |
| `email.username` | `IMAP_USERNAME` | IMAP username. |
| `email.password` | `IMAP_PASSWORD` | IMAP password or app-specific password. |
| `email.mailbox` | `IMAP_MAILBOX` | Mailbox to inspect (defaults to `INBOX`). |
| `ai_parser.api_key` | `AI_PARSER_API_KEY` | API key for the AI parsing service. |
| `ai_parser.endpoint` | `AI_PARSER_ENDPOINT` | HTTPS endpoint of the AI parsing service. |
| `queue.database_path` | `QUEUE_DATABASE_PATH` | File path to the SQLite queue database. |

## Usage

Install dependencies and run the CLI using `python -m idoklad_helper.cli`.

```bash
python -m idoklad_helper.cli pdfco-balance
python -m idoklad_helper.cli check-emails
python -m idoklad_helper.cli process-queue
```

Each command produces structured log output describing its progress.

## Development

Install the optional development dependencies and run the test suite:

```bash
pip install -e .[dev]
pytest
```
