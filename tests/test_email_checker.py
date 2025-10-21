from email.message import EmailMessage
from idoklad_helper.email_checker import EmailChecker, extract_plain_text


def test_extract_plain_text_handles_plain_message():
    msg = EmailMessage()
    msg.set_content("Hello")
    assert extract_plain_text(msg) == "Hello\n"


def test_fetch_unseen(monkeypatch):
    # Prepare fake IMAP connection
    stored = []

    class FakeIMAP:
        def __init__(self, *args, **kwargs):
            pass

        def __enter__(self):
            return self

        def __exit__(self, exc_type, exc, tb):
            return False

        def login(self, username, password):
            assert username == "user"
            assert password == "pass"

        def select(self, mailbox):
            assert mailbox == "INBOX"

        def search(self, *args):
            return "OK", [b"1"]

        def fetch(self, num, *_):
            message = EmailMessage()
            message["Subject"] = "Invoice"
            message.set_content("Body")
            return "OK", [(b"1", message.as_bytes())]

        def store(self, num, flags, value):
            stored.append((num, flags, value))

        def close(self):
            pass

    monkeypatch.setattr("imaplib.IMAP4_SSL", lambda *args, **kwargs: FakeIMAP())

    checker = EmailChecker(host="imap", port=993, username="user", password="pass")
    messages = checker.fetch_unseen()
    assert len(messages) == 1
    assert stored
