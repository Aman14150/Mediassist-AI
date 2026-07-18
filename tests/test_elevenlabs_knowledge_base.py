from pathlib import Path
import unittest


ROOT = Path(__file__).resolve().parents[1]
KB = ROOT / "elevenlabs-knowledge-base"


class ElevenLabsKnowledgeBaseTests(unittest.TestCase):
    def test_curated_upload_package_has_exactly_five_focused_documents(self):
        files = sorted(KB.glob("*.txt"))
        self.assertEqual([file.name for file in files], [
            "01_Hospital_Policies_FAQ.txt",
            "02_Services_Routing.txt",
            "03_Doctors.txt",
            "04_Booking_Payment.txt",
            "05_Approved_Self_Care.txt",
        ])
        for file in files:
            self.assertGreater(file.stat().st_size, 500, file.name)

    def test_psychology_and_crisis_routing_are_present(self):
        routing = (KB / "02_Services_Routing.txt").read_text(encoding="utf-8")
        self_care = (KB / "05_Approved_Self_Care.txt").read_text(encoding="utf-8")
        routing_lower = routing.lower()
        for phrase in ("psychology", "overthinking", "suicide", "self-harm", "immediate danger"):
            self.assertIn(phrase, routing_lower)
        self.assertIn("Never combine, expand, personalize", self_care)

    def test_prompt_requires_exact_approved_responses(self):
        prompt = (ROOT / "ELEVENLABS_SYSTEM_PROMPT.md").read_text(encoding="utf-8")
        self.assertIn("05_Approved_Self_Care.txt", prompt)
        self.assertIn("Never invent, expand, combine, or personalize", prompt)


if __name__ == "__main__":
    unittest.main()
