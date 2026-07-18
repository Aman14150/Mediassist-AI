from pathlib import Path
import unittest


ROOT = Path(__file__).resolve().parents[1]


class AgentRoutingTests(unittest.TestCase):
    def test_fever_alone_routes_to_general_physician(self):
        prompt = (ROOT / "ELEVENLABS_SYSTEM_PROMPT.md").read_text(encoding="utf-8")
        routing = (ROOT / "knowledge-base" / "07_Service_Routing.txt").read_text(encoding="utf-8")

        self.assertIn("Fever alone", prompt)
        self.assertIn("routes to General Physician", prompt)
        self.assertIn("Fever alone is not an Emergency Symptom", routing)

    def test_explicit_red_flags_still_trigger_emergency_guidance(self):
        prompt = (ROOT / "ELEVENLABS_SYSTEM_PROMPT.md").read_text(encoding="utf-8")

        for red_flag in ("chest pain", "severe breathing difficulty", "fainting", "heavy bleeding", "stroke symptoms"):
            self.assertIn(red_flag, prompt)
        self.assertIn("emergency guidance overrides routing", prompt)

    def test_unlisted_non_crisis_mental_health_concerns_offer_general_physician(self):
        prompt = (ROOT / "ELEVENLABS_SYSTEM_PROMPT.md").read_text(encoding="utf-8")
        routing = (ROOT / "knowledge-base" / "07_Service_Routing.txt").read_text(encoding="utf-8")

        for phrase in ("overthinking", "Psychology and Psychiatry are not currently listed", "offer General Physician"):
            self.assertIn(phrase, prompt)
            self.assertIn(phrase, routing)

    def test_self_harm_is_an_emergency_exception(self):
        prompt = (ROOT / "ELEVENLABS_SYSTEM_PROMPT.md").read_text(encoding="utf-8")

        self.assertIn("suicide", prompt)
        self.assertIn("self-harm", prompt)
        self.assertIn("immediate danger to self or others", prompt)
