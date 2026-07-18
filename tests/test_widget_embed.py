from pathlib import Path
import unittest


ROOT = Path(__file__).resolve().parents[1]


class WidgetEmbedTests(unittest.TestCase):
    def test_shared_script_embeds_published_agent(self):
        main_js = (ROOT / "assets" / "js" / "main.js").read_text(encoding="utf-8")
        self.assertIn("agent_2101kxsgx5q2epnvtx3bxm0fjkph", main_js)
        self.assertIn("@elevenlabs/convai-widget-embed", main_js)
        self.assertIn("document.querySelector('elevenlabs-convai')", main_js)

    def test_every_public_html_page_loads_shared_script(self):
        for page in ROOT.glob("*.html"):
            html = page.read_text(encoding="utf-8")
            self.assertIn("assets/js/main.js", html, page.name)

    def test_scroll_button_does_not_overlap_widget(self):
        main_css = (ROOT / "assets" / "css" / "main.css").read_text(encoding="utf-8")
        scroll_rule = main_css.split(".scroll-top {", 1)[1].split("}", 1)[0]
        self.assertIn("left: 15px;", scroll_rule)
        self.assertNotIn("right: 15px;", scroll_rule)
