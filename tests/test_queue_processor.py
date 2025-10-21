from email.message import EmailMessage

from idoklad_helper.queue_processor import QueueProcessor


class DummyPDFCo:
    def __init__(self):
        self.calls = 0

    def get_credit_balance(self):
        self.calls += 1
        return 42

    def ensure_ai_parser_allocation(self, parser):
        parser.ping()


class DummyAIClient:
    def __init__(self):
        self.pings = 0
        self.parses = []

    def ping(self):
        self.pings += 1

    def parse(self, content, metadata):
        self.parses.append((content, metadata))
        return {"ok": True}


def test_queue_processor_enqueues(tmp_path):
    db = tmp_path / "queue.db"
    pdf = DummyPDFCo()
    ai = DummyAIClient()
    processor = QueueProcessor(database_path=db, pdfco_client=pdf, ai_client=ai)

    message = EmailMessage()
    message["Subject"] = "Invoice"
    message["From"] = "billing@example.com"
    message.set_content("Hello world")

    assert processor.enqueue_messages([message]) == 1
    rows = processor.pending_jobs()
    assert len(rows) == 1


def test_process_pending(tmp_path):
    db = tmp_path / "queue.db"
    pdf = DummyPDFCo()
    ai = DummyAIClient()
    processor = QueueProcessor(database_path=db, pdfco_client=pdf, ai_client=ai)

    message = EmailMessage()
    message["Subject"] = "Invoice"
    message["From"] = "billing@example.com"
    message.set_content("Hello world")

    processor.enqueue_messages([message])
    assert processor.process_pending() == 1
    assert pdf.calls == 1
    assert ai.pings == 1
    assert ai.parses[0][1]["subject"] == "Invoice"
