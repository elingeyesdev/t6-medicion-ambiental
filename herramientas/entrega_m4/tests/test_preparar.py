"""Escenarios adversos y publicación offline; no consulta GitHub ni CI remoto."""

from contextlib import redirect_stderr, redirect_stdout
from copy import deepcopy
from datetime import datetime, timedelta, timezone
import io
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch

from herramientas.entrega_m4 import preparar


ROOT = Path(__file__).resolve().parents[3]
FIXTURE = ROOT / "herramientas/entrega_m4/fixtures/snapshot.json"
NOW = datetime(2026, 10, 31, 1, tzinfo=timezone.utc)


class SnapshotCase(unittest.TestCase):
    def setUp(self):
        self.data, self.digest = preparar.read_snapshot(FIXTURE)

    def issue(self, number):
        return next(i for i in self.data["issues"] if i["number"] == number)

    def pull(self, number=120):
        return next(p for p in self.data["pull_requests"] if p["number"] == number)

    def report(self, now=NOW):
        return preparar.build_report(self.data, self.digest, now)

    def row(self, report, number):
        return next(row for group in report["groups"].values() for row in group if row["number"] == number)

    def review(self, state="APPROVED", sha=None, by="fixture-reviewer", id=1, at="2026-10-30T23:00:00Z"):
        return {"id": id, "by": by, "state": state,
                "commit_sha": sha or self.pull()["head_sha"], "submitted_at": at}

    def complete_pull(self, pr):
        pr.update(state="closed", merged=True, merged_at="2026-10-30T22:00:00Z", draft=False,
                  reviews_complete=True,
                  reviews=[self.review(sha=pr["head_sha"])],
                  checks={"status": "success", "sha": pr["head_sha"],
                          "evidence_url": "https://github.com/elingeyesdev/t6-medicion-ambiental/actions/runs/1"})

    def complete_evidence(self):
        # Mundo hipotético: incluso con todos los campos favorables no se emite entrega.
        self.data["source"] = "provided_snapshot"
        self.data["validation"] = {"by": "fixture-validator", "at": "2026-10-31T00:05:00Z"}
        for issue in self.data["issues"]:
            issue.update(state="closed", implementation="implemented", engineer_acceptance={
                "status": "accepted", "by": "fixture-engineer", "at": "2026-10-30T23:00:00Z",
                "evidence_url": f"https://github.com/elingeyesdev/t6-medicion-ambiental/issues/{issue['number']}#issuecomment-1",
            })
            if not issue["pull_requests"]:
                number = issue["number"] + 1000
                pr = deepcopy(self.pull())
                pr.update(number=number, issues=[issue["number"]])
                self.data["pull_requests"].append(pr)
                issue["pull_requests"] = [number]
        for pr in self.data["pull_requests"]:
            self.complete_pull(pr)


class Scenario(SnapshotCase):
    def test_baseline_keeps_m3_open_and_m4_in_preparation(self):
        report = self.report()
        self.assertEqual(report["purpose"], "PREPARACION_118")
        self.assertEqual(report["delivery_status"], "not_evaluated")
        self.assertTrue(all(self.row(report, n)["state_reported"] == "open" for n in (39, 40, 41)))
        self.assertTrue({39, 40, 41}.isdisjoint({r["number"] for r in report["groups"]["M4"]}))
        self.assertEqual(self.row(report, 110)["implementation_reported"], "proposal")
        self.assertIn("Jaziel", self.row(report, 109)["context_note_2026_10_31"])
        self.assertIn("Flutter", self.row(report, 114)["context_note_2026_10_31"])
        self.assertEqual(report["context_as_of"], "2026-10-31")
        self.assertIn("Este informe no certifica producto de agua ni entrega final.", report["scope_constraints"])
        self.assertNotIn("schedule", report)

    def test_approved_open_pr_is_still_not_merged(self):
        pr = self.pull()
        self.complete_pull(pr)
        pr.update(state="open", merged=False, merged_at=None)
        result = next(p for p in self.report()["pull_requests"] if p["number"] == 120)
        self.assertEqual(result["review_observation"], "current_sha_approval_reported")
        self.assertEqual(result["merge_observation"], "open")
        self.assertIn("merge_not_confirmed", result["pending_codes"])

    def test_closed_pr_without_merge_remains_unmerged(self):
        self.pull()["state"] = "closed"
        result = next(p for p in self.report()["pull_requests"] if p["number"] == 120)
        self.assertEqual(result["merge_observation"], "closed_unmerged")

    def test_inconsistent_merge_metadata_is_preserved_and_flagged(self):
        self.pull().update(merged=True, merged_at="2026-10-30T22:00:00Z")
        result = next(p for p in self.report()["pull_requests"] if p["number"] == 120)
        self.assertTrue(result["merged_reported"])
        self.assertEqual(result["state_reported"], "open")
        self.assertEqual(result["merge_observation"], "contradictory")

    def test_approved_old_sha_does_not_approve_head(self):
        old_sha = "c" * 40
        self.pull()["reviews"] = [self.review(sha=old_sha)]
        result = next(p for p in self.report()["pull_requests"] if p["number"] == 120)
        self.assertEqual(result["review_observation"], "outdated_approval")
        self.assertEqual(result["current_sha_approvals_reported"], [])
        self.assertEqual(result["reviews_reported"][0]["commit_sha"], old_sha)
        self.assertIn(old_sha, preparar.markdown_index(self.report()))

    def test_new_changes_or_dismissal_invalidates_same_reviewer_approval(self):
        for state in ("CHANGES_REQUESTED", "DISMISSED"):
            with self.subTest(state=state):
                self.pull()["reviews"] = [self.review(), self.review(state, id=2, at="2026-10-30T23:30:00Z")]
                result = next(p for p in self.report()["pull_requests"] if p["number"] == 120)
                self.assertNotEqual(result["review_observation"], "current_sha_approval_reported")
                self.assertEqual(result["current_sha_approvals_reported"], [])

    def test_another_reviewer_requesting_changes_still_blocks(self):
        self.pull()["reviews"] = [self.review(), self.review("CHANGES_REQUESTED", by="other-reviewer", id=2)]
        result = next(p for p in self.report()["pull_requests"] if p["number"] == 120)
        self.assertEqual(result["review_observation"], "changes_requested")

    def test_comment_does_not_revoke_prior_approval(self):
        self.pull()["reviews"] = [self.review(), self.review("COMMENTED", id=2, at="2026-10-30T23:30:00Z")]
        result = next(p for p in self.report()["pull_requests"] if p["number"] == 120)
        self.assertEqual(result["review_observation"], "current_sha_approval_reported")

    def test_partial_or_undated_review_history_stays_unknown(self):
        self.pull()["reviews"] = [self.review()]
        self.pull()["reviews_complete"] = False
        result = next(p for p in self.report()["pull_requests"] if p["number"] == 120)
        self.assertEqual(result["review_observation"], "unknown")
        self.pull()["reviews_complete"] = True
        self.pull()["reviews"][0]["submitted_at"] = None
        result = next(p for p in self.report()["pull_requests"] if p["number"] == 120)
        self.assertEqual(result["review_observation"], "unknown")

    def test_unknown_checks_never_become_success(self):
        result = next(p for p in self.report()["pull_requests"] if p["number"] == 120)
        self.assertEqual(result["checks_observation"], "unknown")
        self.assertIn("checks_not_confirmed_success", result["pending_codes"])

    def test_check_success_requires_current_sha_and_reference(self):
        self.complete_pull(self.pull())
        good = deepcopy(self.pull()["checks"])
        for change in ({"sha": "d" * 40}, {"evidence_url": None}, {"sha": None}):
            with self.subTest(change=change):
                self.pull()["checks"] = {**good, **change}
                result = next(p for p in self.report()["pull_requests"] if p["number"] == 120)
                self.assertEqual(result["checks_reported"]["status"], "success")
                self.assertEqual(result["checks_observation"], "unknown")

    def test_120_new_head_after_scl_tests_does_not_inherit_previous_ci(self):
        self.complete_pull(self.pull())
        previous_sha = self.pull()["head_sha"]
        self.pull().update(title="FIXTURE: 24 pruebas, 8 parejas SCL y metadata radar", head_sha="e" * 40,
                           merged=False, merged_at=None, state="open")
        report = self.report()
        result = next(p for p in report["pull_requests"] if p["number"] == 120)
        self.assertEqual(result["checks_reported"]["sha"], previous_sha)
        self.assertEqual(result["checks_observation"], "unknown")
        self.assertEqual(result["review_observation"], "outdated_approval")
        self.assertIn("pull_request_pending", self.row(report, 110)["pending_codes"])

    def test_november_5_target_is_not_promoted_to_academic_deadline(self):
        report = self.report()
        m4 = next(m for m in report["milestones_reported"] if m["phase"] == "M4")
        self.assertEqual(m4["target_date"], "2026-11-05")
        self.assertIs(m4["academic_deadline_confirmed"], False)
        self.assertNotIn("due_date", self.row(report, 118))

    def test_closed_issue_is_not_engineer_delivery(self):
        self.issue(110).update(state="closed", implementation="implemented")
        self.complete_pull(self.pull())
        result = self.row(self.report(), 110)
        self.assertIn("closed_issue_is_not_delivery", result["pending_codes"])
        self.assertIn("engineer_acceptance_not_confirmed", result["pending_codes"])

    def test_acceptance_without_reference_is_not_confirmed(self):
        self.issue(110)["engineer_acceptance"].update(status="accepted", by="fixture-engineer", at="2026-10-30T23:00:00Z")
        self.assertIn("engineer_acceptance_not_confirmed", self.row(self.report(), 110)["pending_codes"])

    def test_missing_dependency_is_reported_and_propagates_to_118(self):
        self.data["issues"] = [i for i in self.data["issues"] if i["number"] != 39]
        report = self.report()
        self.assertIn(39, report["missing_issues"])
        self.assertIn(39, self.row(report, 111)["unresolved_dependencies"])
        self.assertIn(111, self.row(report, 112)["unresolved_dependencies"])
        self.assertIn(117, self.row(report, 118)["unresolved_dependencies"])

    def test_omitting_baseline_edges_does_not_remove_blockers(self):
        self.issue(118)["dependencies"] = []
        row = self.row(self.report(), 118)
        self.assertEqual(row["dependencies_reported"], [])
        self.assertEqual(row["dependencies_considered"], [41, 109, 115, 117])
        self.assertIn("baseline_dependencies_omitted", row["pending_codes"])

    def test_uncaptured_dependencies_are_unknown_not_empty(self):
        self.issue(109)["dependencies"] = None
        row = self.row(self.report(), 109)
        self.assertIsNone(row["dependencies_reported"])
        self.assertIn("dependencies_unknown", row["pending_codes"])

    def test_dependency_cycle_requires_human_review(self):
        self.issue(111)["dependencies"].append(112)
        report = self.report()
        for n in (111, 112):
            self.assertIn("dependency_cycle", self.row(report, n)["pending_codes"])

    def test_wrong_milestone_does_not_move_m3_into_m4(self):
        self.issue(41)["milestone"] = 4
        report = self.report()
        row = self.row(report, 41)
        self.assertEqual(row["phase_reported"], "M4")
        self.assertEqual(row["group"], "M3")
        self.assertIn("milestone_mismatch", row["pending_codes"])
        self.assertNotIn(41, [i["number"] for i in report["groups"]["M4"]])

    def test_missing_pr_or_nonreciprocal_links_remain_pending(self):
        self.issue(110)["pull_requests"] = [999]
        report = self.report()
        self.assertIn("pull_request_missing", self.row(report, 110)["pending_codes"])
        self.assertIn("baseline_pr_link_missing", {i["code"] for i in report["pending"]})

    def test_stale_snapshot_preserves_uncertainty_even_with_all_favorable_fields(self):
        self.complete_evidence()
        report = self.report(now=NOW + timedelta(days=2))
        self.assertEqual(report["freshness"], "stale")
        self.assertEqual(self.row(report, 118)["dependency_assessment"], "unknown_snapshot")
        self.assertEqual(self.row(report, 118)["unresolved_dependencies"], [41, 109, 115, 117])
        self.assertEqual(report["delivery_status"], "not_evaluated")

    def test_future_capture_is_not_current(self):
        self.data["captured_at"] = "2026-10-31T02:00:00Z"
        self.assertEqual(self.report()["freshness"], "future")

    def test_review_after_capture_cannot_approve(self):
        self.pull()["reviews"] = [self.review(at="2026-10-31T00:30:00Z")]
        result = next(p for p in self.report()["pull_requests"] if p["number"] == 120)
        self.assertEqual(result["review_observation"], "unknown")
        self.assertIn("review_after_capture", result["pending_codes"])

    def test_favorable_but_post_capture_issue_and_pr_do_not_clear_dependencies(self):
        self.complete_evidence()
        self.issue(110)["updated_at"] = "2026-10-31T00:30:00Z"
        self.pull()["updated_at"] = "2026-10-31T00:30:00Z"
        report = self.report()
        self.assertIn("record_after_capture", self.row(report, 110)["pending_codes"])
        self.assertIn(110, self.row(report, 111)["unresolved_dependencies"])

    def test_all_favorable_fields_still_do_not_certify_m4(self):
        self.complete_evidence()
        report = self.report()
        self.assertEqual(report["pending_count"], 0)
        self.assertEqual(report["delivery_status"], "not_evaluated")
        self.assertIn("no acredita entrega", preparar.markdown_index(report))

    def test_technical_completion_without_academic_acceptance_unblocks_dependents(self):
        self.complete_evidence()
        for issue in self.data["issues"]:
            issue["engineer_acceptance"] = {"status": "pending", "by": None, "at": None, "evidence_url": None}
        report = self.report()
        for number in (40, 110, 112, 114, 118):
            row = self.row(report, number)
            self.assertEqual(row["technical_pending_codes"], [])
            self.assertEqual(row["unresolved_dependencies"], [])
            self.assertIn("engineer_acceptance_not_confirmed", row["delivery_warning_codes"])
            self.assertIn("closed_issue_is_not_delivery", row["delivery_warning_codes"])
        self.assertEqual(report["delivery_status"], "not_evaluated")
        self.assertGreater(report["pending_count"], 0)

    def test_native_110_and_mobile_114_edges_cannot_be_omitted(self):
        for number, dependency in ((110, 40), (114, 112)):
            with self.subTest(number=number):
                self.issue(number)["dependencies"] = []
                row = self.row(self.report(), number)
                self.assertIn(dependency, row["dependencies_considered"])
                self.assertIn("baseline_dependencies_omitted", row["technical_pending_codes"])

    def test_later_snapshot_uses_current_state_and_dates_historical_context(self):
        self.complete_evidence()
        self.data["captured_at"] = "2026-11-03T00:00:00Z"
        self.data["validation"]["at"] = "2026-11-03T00:05:00Z"
        report = self.report(now=NOW + timedelta(days=3))
        self.assertEqual(self.row(report, 110)["implementation_reported"], "implemented")
        self.assertEqual(self.row(report, 109)["technical_pending_codes"], [])
        self.assertEqual(report["pending_count"], 0)
        self.assertEqual(report["context_as_of"], "2026-10-31")
        self.assertIn("Contexto comunicado al 31/10/2026", preparar.markdown_index(report))

    def test_invalid_and_credential_bearing_input_is_rejected_without_echo(self):
        fake_secret = "ghp_SYNTHETIC_CREDENTIAL_1234567890"
        changes = (
            lambda d: d["issues"][0].update(number=True),
            lambda d: d["pull_requests"][0].update(head_sha="abc123"),
            lambda d: d["issues"].append(deepcopy(d["issues"][0])),
            lambda d: d.update(token=fake_secret),
            lambda d: d["issues"][0].update(title=fake_secret),
            lambda d: d["issues"][0].update(title="https://username:password@example.test"),
            lambda d: d["issues"][0]["engineer_acceptance"].update(evidence_url="https://github.com/other/repo/issues/1"),
            lambda d: d["issues"][0]["engineer_acceptance"].update(evidence_url="https://github.com/elingeyesdev/t6-medicion-ambiental/issues/1?token=private"),
            lambda d: d.update(captured_at="2026-02-30T00:00:00Z"),
            lambda d: d["milestones"].append(deepcopy(d["milestones"][0])),
        )
        for change in changes:
            with self.subTest(change=change):
                data = deepcopy(self.data)
                change(data)
                with self.assertRaises(preparar.SnapshotError) as raised:
                    preparar.validate_snapshot(data)
                self.assertNotIn(fake_secret, str(raised.exception))
                self.assertNotIn("password@example", str(raised.exception))

    def test_invalid_json_duplicate_keys_and_nonfinite_numbers_are_rejected(self):
        for raw in ('{"schema_version":1,"schema_version":1}', '{"field":NaN}', "not-json", '{"field":' + '9' * 5000 + '}'):
            with self.subTest(raw=raw), tempfile.TemporaryDirectory() as temp:
                path = Path(temp) / "snapshot.json"
                path.write_text(raw, encoding="utf-8")
                with self.assertRaises(preparar.SnapshotError):
                    preparar.read_snapshot(path)

    def test_utf8_bom_from_powershell_and_digest_of_original_bytes(self):
        import hashlib
        with tempfile.TemporaryDirectory() as temp:
            path = Path(temp) / "snapshot.json"
            raw = b"\xef\xbb\xbf" + FIXTURE.read_bytes()
            path.write_bytes(raw)
            data, digest = preparar.read_snapshot(path)
            self.assertEqual(data, self.data)
            self.assertEqual(digest, hashlib.sha256(raw).hexdigest())

    def test_invalid_age_threshold_has_safe_error(self):
        for hours in (0, -1, float("nan"), float("inf"), 1e100):
            with self.subTest(hours=hours), self.assertRaises(preparar.SnapshotError):
                preparar.build_report(self.data, self.digest, NOW, hours)

    def test_markdown_escapes_supplied_html_and_links(self):
        self.issue(110)["title"] = '<img src=x> [claim](https://example.test)'
        index = preparar.markdown_index(self.report())
        self.assertNotIn("<img", index)
        self.assertNotIn("[claim]", index)
        self.assertIn("&lt;img", index)


class Publication(SnapshotCase):
    def test_transient_windows_lock_retries_and_publishes_without_replacement(self):
        with tempfile.TemporaryDirectory() as temp:
            source, target = Path(temp) / "staging", Path(temp) / "published"
            source.mkdir()
            (source / "evidence").write_text("fixture", encoding="utf-8")
            original = os.rename
            calls = []

            def transient_lock(origin, destination):
                calls.append(destination)
                if len(calls) == 1:
                    error = PermissionError(13, "simulated Windows file lock")
                    error.winerror = 5
                    raise error
                original(origin, destination)

            with patch.object(preparar.sys, "platform", "win32"), \
                    patch.object(preparar.os, "rename", side_effect=transient_lock), \
                    patch.object(preparar.time, "sleep") as sleep:
                preparar._rename_without_replacement(source, target)
            self.assertEqual(len(calls), 2)
            sleep.assert_called_once_with(0.05)
            self.assertEqual((target / "evidence").read_text(encoding="utf-8"), "fixture")

    def test_windows_lock_error_with_existing_target_is_never_retried(self):
        with tempfile.TemporaryDirectory() as temp:
            source, target = Path(temp) / "staging", Path(temp) / "previous"
            source.mkdir()
            target.mkdir()
            error = PermissionError(13, "simulated Windows file lock")
            error.winerror = 5
            with patch.object(preparar.sys, "platform", "win32"), \
                    patch.object(preparar.os, "rename", side_effect=error) as rename, \
                    patch.object(preparar.time, "sleep") as sleep:
                with self.assertRaises(PermissionError):
                    preparar._rename_without_replacement(source, target)
            rename.assert_called_once()
            sleep.assert_not_called()
            self.assertEqual(list(target.iterdir()), [])

    def test_atomic_publication_exposes_both_files_together(self):
        report = self.report()
        with tempfile.TemporaryDirectory() as temp:
            target = Path(temp) / "preparacion"
            original = preparar._rename_without_replacement

            def observe(staging, destination):
                self.assertFalse(target.exists())
                self.assertEqual({p.name for p in Path(staging).iterdir()}, {"indice.md", "resumen.json"})
                original(staging, destination)

            with patch.object(preparar, "_rename_without_replacement", side_effect=observe):
                preparar.publish_report(report, target)
            self.assertEqual({p.name for p in target.iterdir()}, {"indice.md", "resumen.json"})
            self.assertEqual(json.loads((target / "resumen.json").read_text(encoding="utf-8")), report)
            self.assertEqual([p.name for p in Path(temp).iterdir()], ["preparacion"])

    def test_existing_empty_or_nonempty_directory_and_file_are_preserved(self):
        with tempfile.TemporaryDirectory() as temp:
            for name, kind in (("empty", "empty"), ("previous", "nonempty"), ("file", "file")):
                with self.subTest(kind=kind):
                    target = Path(temp) / name
                    if kind == "file":
                        target.write_text("original", encoding="utf-8")
                    else:
                        target.mkdir()
                        if kind == "nonempty":
                            (target / "original").write_text("original", encoding="utf-8")
                    with self.assertRaises(FileExistsError):
                        preparar.publish_report(self.report(), target)
                    if kind == "empty":
                        self.assertEqual(list(target.iterdir()), [])
                    else:
                        self.assertEqual((target if kind == "file" else target / "original").read_text(encoding="utf-8"), "original")

    def test_race_with_existing_empty_directory_does_not_replace_it(self):
        with tempfile.TemporaryDirectory() as temp:
            target = Path(temp) / "raced"
            original = preparar._rename_without_replacement

            def create_competing_directory(staging, destination):
                Path(destination).mkdir()
                original(staging, destination)

            with patch.object(preparar, "_rename_without_replacement", side_effect=create_competing_directory):
                with self.assertRaises(OSError):
                    preparar.publish_report(self.report(), target)
            self.assertTrue(target.is_dir())
            self.assertEqual(list(target.iterdir()), [])
            self.assertEqual([p.name for p in Path(temp).iterdir()], ["raced"])

    def test_interrupted_write_removes_only_staging(self):
        with tempfile.TemporaryDirectory() as temp:
            sentinel = Path(temp) / "previous"
            sentinel.mkdir()
            (sentinel / "original").write_bytes(b"untouched")
            target = Path(temp) / "new"
            original = Path.open

            def fail_json_write(path, *args, **kwargs):
                if path.name == "resumen.json":
                    raise OSError("simulated disk full")
                return original(path, *args, **kwargs)

            with patch.object(Path, "open", autospec=True, side_effect=fail_json_write):
                with self.assertRaises(OSError):
                    preparar.publish_report(self.report(), target)
            self.assertFalse(target.exists())
            self.assertEqual((sentinel / "original").read_bytes(), b"untouched")
            self.assertEqual([p.name for p in Path(temp).iterdir()], ["previous"])

    def test_cli_works_without_network_gh_or_reading_credentials(self):
        fake_secret = "SYNTHETIC_ENV_CREDENTIAL_DO_NOT_CAPTURE"
        with tempfile.TemporaryDirectory() as temp:
            target = Path(temp) / "report"
            out, err = io.StringIO(), io.StringIO()
            with patch("socket.socket", side_effect=AssertionError("network forbidden")), \
                    patch("subprocess.Popen", side_effect=AssertionError("subprocess forbidden")), \
                    patch.dict(os.environ, {"PATH": "", "GH_TOKEN": fake_secret, "GITHUB_TOKEN": fake_secret}), \
                    redirect_stdout(out), redirect_stderr(err):
                code = preparar.main(["--snapshot", str(FIXTURE), "--output", str(target),
                                      "--fixture-now", "2026-10-31T01:00:00Z", "--fail-on-pending"])
            self.assertEqual(code, 3)
            self.assertTrue(target.is_dir())
            for content in (out.getvalue(), err.getvalue(), *(p.read_text(encoding="utf-8") for p in target.iterdir())):
                self.assertNotIn(fake_secret, content)
            self.assertEqual(err.getvalue(), "")

    def test_real_snapshot_cannot_use_fixture_clock(self):
        self.data["source"] = "provided_snapshot"
        with tempfile.TemporaryDirectory() as temp:
            path = Path(temp) / "snapshot.json"
            path.write_text(json.dumps(self.data), encoding="utf-8")
            target = Path(temp) / "report"
            with redirect_stderr(io.StringIO()):
                code = preparar.main(["--snapshot", str(path), "--output", str(target), "--fixture-now", "2026-10-31T01:00:00Z"])
            self.assertEqual(code, 2)
            self.assertFalse(target.exists())

    def test_module_entrypoint_produces_preparation_only_in_subprocess(self):
        with tempfile.TemporaryDirectory() as temp:
            target = Path(temp) / "report"
            result = subprocess.run([sys.executable, "-B", "-m", "herramientas.entrega_m4", "--snapshot", str(FIXTURE),
                                     "--output", str(target), "--fixture-now", "2026-10-31T01:00:00Z"],
                                    cwd=ROOT, capture_output=True, text=True, timeout=20)
            self.assertEqual(result.returncode, 0, result.stderr)
            self.assertEqual({p.suffix for p in target.iterdir()}, {".md", ".json"})
            self.assertIn("Entrega M4 no evaluada", result.stdout)


if __name__ == "__main__":
    unittest.main()
