#!/usr/bin/env python3
"""Validate the shipped JSON examples against their Draft 2020-12 schemas."""

from __future__ import annotations

import json
from pathlib import Path
import sys
from datetime import datetime
import argparse

try:
    from jsonschema import Draft202012Validator, FormatChecker
except ImportError:
    print("Install the development dependency with: python -m pip install jsonschema", file=sys.stderr)
    raise SystemExit(2)


ROOT = Path(__file__).resolve().parents[1]
CASES = (
    (
        ROOT / "schema" / "cinemashowtimes-json-1.1.schema.json",
        ROOT / "examples" / "CinemaShowtimes-1.1-example.json",
    ),
    (
        ROOT / "schema" / "cinemashowtimes-seatmap-1.0.schema.json",
        ROOT / "examples" / "seatmap-1.0-example.json",
    ),
)


def parse_datetime(value: str) -> datetime:
    return datetime.fromisoformat(value.replace("Z", "+00:00"))


def semantic_feed_errors(feed: dict) -> list[str]:
    """Checks relationships that JSON Schema cannot express portably."""
    errors: list[str] = []
    films = {item["film_id"] for item in feed.get("films", [])}
    auditoria = {item["auditorium_id"]: item for item in feed.get("auditoria", [])}
    cinema_items = feed.get("cinemas") or ([feed["cinema"]] if "cinema" in feed else [])
    cinemas = {item["cinema_id"] for item in cinema_items}

    for key, items, id_key in (
        ("films", feed.get("films", []), "film_id"),
        ("auditoria", feed.get("auditoria", []), "auditorium_id"),
        ("sessions", feed.get("sessions", []), "session_id"),
    ):
        ids = [item[id_key] for item in items]
        if len(ids) != len(set(ids)):
            errors.append(f"{key}: duplicate {id_key}")

    generated = parse_datetime(feed["generated_at"])
    if "expires_at" in feed and parse_datetime(feed["expires_at"]) <= generated:
        errors.append("expires_at must be later than generated_at")

    for session in feed.get("sessions", []):
        prefix = f"session {session['session_id']}"
        if session["film_id"] not in films:
            errors.append(f"{prefix}: unknown film_id {session['film_id']}")
        auditorium_id = session.get("auditorium_id")
        if auditorium_id and auditorium_id not in auditoria:
            errors.append(f"{prefix}: unknown auditorium_id {auditorium_id}")
        if session.get("cinema_id") and session["cinema_id"] not in cinemas:
            errors.append(f"{prefix}: unknown cinema_id {session['cinema_id']}")
        elif len(cinemas) > 1 and not session.get("cinema_id"):
            errors.append(f"{prefix}: missing or unknown cinema_id")

        start = parse_datetime(session["start_time"])
        for field in ("feature_start_time", "credits_start_time", "end_time"):
            if field in session and parse_datetime(session[field]) < start:
                errors.append(f"{prefix}: {field} is earlier than start_time")
        if "preshow_start_time" in session and parse_datetime(session["preshow_start_time"]) > start:
            errors.append(f"{prefix}: preshow_start_time is later than start_time")
        if "end_time" in session and "feature_start_time" in session:
            if parse_datetime(session["end_time"]) < parse_datetime(session["feature_start_time"]):
                errors.append(f"{prefix}: end_time is earlier than feature_start_time")

        snapshot = session.get("availability", {})
        total = snapshot.get("seats_total")
        available = snapshot.get("seats_available")
        if total is not None and available is not None and available > total:
            errors.append(f"{prefix}: seats_available exceeds seats_total")

        auditorium = auditoria.get(auditorium_id, {})
        class_ids = {item["class_id"] for item in auditorium.get("seat_classes", [])}
        for offer in session.get("offers", []):
            if offer.get("class_id") and offer["class_id"] not in class_ids:
                errors.append(f"{prefix}: offer {offer['offer_id']} has unknown class_id")
    return errors


def semantic_seatmap_errors(seatmap: dict) -> list[str]:
    seat_ids: list[str] = []
    for section in seatmap["sections"]:
        for row in section["rows"]:
            seat_ids.extend(seat["seat_id"] for seat in row["seats"])
    if len(seat_ids) != len(set(seat_ids)):
        return ["seatmap: duplicate seat_id"]
    return []


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument(
        "--feed",
        action="append",
        default=[],
        type=Path,
        help="Additional CinemaShowtimes-JSON 1.1 document to validate.",
    )
    args = parser.parse_args()
    cases = list(CASES)
    feed_schema = ROOT / "schema" / "cinemashowtimes-json-1.1.schema.json"
    cases.extend((feed_schema, path) for path in args.feed)
    failed = False
    for schema_path, example_path in cases:
        schema = json.loads(schema_path.read_text(encoding="utf-8"))
        instance = json.loads(example_path.read_text(encoding="utf-8"))
        Draft202012Validator.check_schema(schema)
        validator = Draft202012Validator(schema, format_checker=FormatChecker())
        errors = sorted(validator.iter_errors(instance), key=lambda item: list(item.path))
        semantic_errors = (
            semantic_feed_errors(instance)
            if instance.get("spec") == "cinemashowtimes-json/1.1"
            else semantic_seatmap_errors(instance)
        )
        if errors or semantic_errors:
            failed = True
            print(f"{example_path.name}: FAILED")
            for error in errors:
                location = "/".join(str(part) for part in error.absolute_path) or "<root>"
                print(f"  {location}: {error.message}")
            for error in semantic_errors:
                print(f"  {error}")
        else:
            print(f"{example_path.name}: OK")
    return 1 if failed else 0


if __name__ == "__main__":
    raise SystemExit(main())
