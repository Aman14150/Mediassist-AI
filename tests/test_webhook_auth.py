from pathlib import Path
import unittest


ROOT = Path(__file__).resolve().parents[1]


class WebhookAuthenticationTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.security = (ROOT / "app" / "security.php").read_text(encoding="utf-8")

    def test_elevenlabs_auth_supports_direct_secret_header(self):
        self.assertIn("HTTP_X_MEDIASSIST_KEY", self.security)
        self.assertIn("normalize_webhook_secret", self.security)
        self.assertIn("$provided = elevenlabs_webhook_token();", self.security)
        self.assertIn("hash_equals($expected, $provided)", self.security)

    def test_elevenlabs_auth_keeps_bearer_compatibility(self):
        self.assertIn("return bearer_token();", self.security)
        self.assertIn("^Bearer\\s+(.+)$", self.security)
