"""Contract checks for optional motion-poster media in complete 1.1 feeds."""

from copy import deepcopy
import json
from pathlib import Path
import unittest

from jsonschema import Draft202012Validator, FormatChecker


ROOT = Path(__file__).resolve().parents[1]


class MotionPosterContractTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        schema = json.loads(
            (ROOT / "schema/cinemashowtimes-json-1.1.schema.json").read_text()
        )
        Draft202012Validator.check_schema(schema)
        cls.validator = Draft202012Validator(schema, format_checker=FormatChecker())
        cls.example = json.loads(
            (ROOT / "examples/CinemaShowtimes-1.1-example.json").read_text()
        )

    def setUp(self):
        self.feed = deepcopy(self.example)
        self.media = self.feed["films"][0]["media"]
        self.motion = next(item for item in self.media if item["role"] == "motion_poster")

    def test_complete_feed_carries_distinct_poster_trailer_and_motion_poster(self):
        self.validator.validate(self.feed)
        self.assertEqual({item["role"] for item in self.media},
                         {"poster", "trailer", "motion_poster"})
        self.assertEqual(self.motion["kind"], "video")
        self.assertIn("signage", self.motion["rights"]["allowed_uses"])

    def test_feeds_without_motion_posters_remain_valid(self):
        self.media.remove(self.motion)
        self.validator.validate(self.feed)
        self.feed["films"][0].pop("media")
        self.validator.validate(self.feed)

    def test_motion_poster_cannot_be_a_static_image_audio_or_document(self):
        for kind in ("image", "audio", "document"):
            with self.subTest(kind=kind):
                self.motion["kind"] = kind
                errors = list(self.validator.iter_errors(self.feed))
                self.assertTrue(any(error.validator == "const"
                                    and list(error.absolute_path)[-1] == "kind"
                                    for error in errors))

    def test_minimal_motion_asset_does_not_require_optional_metadata(self):
        self.feed["films"][0]["media"] = [{
            key: self.motion[key] for key in ("media_id", "kind", "role", "url")
        }]
        self.validator.validate(self.feed)

    def test_motion_asset_still_requires_identity_kind_and_valid_url(self):
        for field in ("media_id", "kind", "url"):
            with self.subTest(missing=field):
                asset = deepcopy(self.motion)
                del asset[field]
                self.feed["films"][0]["media"] = [asset]
                self.assertFalse(self.validator.is_valid(self.feed))
        for url in ("", "not a URL"):
            with self.subTest(url=url):
                self.feed["films"][0]["media"] = [dict(self.motion, url=url)]
                self.assertFalse(self.validator.is_valid(self.feed))

    def test_dimensions_are_not_restricted_to_a_single_signage_rendition(self):
        for width, height in ((720, 1280), (1000, 1500), (1920, 1080)):
            with self.subTest(dimensions=(width, height)):
                self.motion.update(width=width, height=height)
                self.validator.validate(self.feed)

    def test_readme_complete_feed_matches_the_validated_example(self):
        readme = (ROOT / "README.md").read_text()
        section = readme.split("## Complete example", 1)[1]
        example = json.loads(section.split("```json\n", 1)[1].split("```", 1)[0])
        self.assertEqual(example, self.example)


if __name__ == "__main__":
    unittest.main()
