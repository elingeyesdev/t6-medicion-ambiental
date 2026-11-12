"""Valida un snapshot suministrado y publica un índice de PREPARACIÓN #118.

Solo biblioteca estándar. No ejecuta comandos ni realiza consultas de red.
Las afirmaciones del snapshot se conservan como declaradas, no verificadas aquí.
"""

import argparse
import ctypes
import errno
import hashlib
import html
import json
import math
import os
from pathlib import Path
import re
import shutil
import sys
import tempfile
import time
from datetime import date, datetime, timedelta, timezone
from urllib.parse import urlsplit


EXPECTED_DEPENDENCIES = {
    110: (40,), 111: (39, 110), 112: (111,), 113: (112, 47),
    114: (112,), 115: (111, 113, 114),
    117: (115, 116), 118: (41, 109, 115, 117),
}
EXPECTED_PHASES = {**dict.fromkeys((39, 40, 41), "M3"),
                   **dict.fromkeys(range(109, 119), "M4")}
EXPECTED_PULLS = {120: 110, 119: 116}
CONTEXT_NOTES_20261031 = {
    39: "Se comunicaron tres fallos Flutter y cambios solicitados en b176111; confirmar SHA completo y vigencia.",
    109: "Planificación pendiente de Jaziel y capacidad; sin cronograma confirmado.",
    110: "Propuesta de inundaciones / PR #120; recapturar HEAD tras ampliar pruebas SCL y metadata radar. No acredita producto de agua ni CI actual.",
    114: "Flutter a cargo de Jaziel; requiere su implementación y evidencia propia.",
    116: "Forestal / PR #119 en revisión; no acredita aprobación ni integración.",
    118: "Preparar documentos no completa M4 ni la entrega al ingeniero.",
}
SHA = re.compile(r"[0-9a-f]{40}\Z")
LOGIN = re.compile(r"[A-Za-z0-9][A-Za-z0-9-]{0,38}\Z")
REPOSITORY = re.compile(r"[A-Za-z0-9][A-Za-z0-9-]{0,38}/[A-Za-z0-9_.-]{1,100}\Z")
SECRET = re.compile(
    r"(?:gh[pousr]_[A-Za-z0-9_]{8,}|github_pat_[A-Za-z0-9_]{8,}"
    r"|\bBearer\s+\S+|-----BEGIN [A-Z ]*PRIVATE KEY-----"
    r"|\b(?:token|password|passwd|secret|api[_-]?key|authorization)\s*[:=]\s*\S+"
    r"|https?://[^\s/@]+:[^\s/@]+@"
    r"|\bAKIA[A-Z0-9]{16}\b|\bsk-[A-Za-z0-9_-]{16,}"
    r"|\bxox[baprs]-[A-Za-z0-9-]{10,})", re.IGNORECASE,
)
MAX_BYTES = 5 * 1024 * 1024


class SnapshotError(ValueError):
    """Error seguro: nunca incluye los valores recibidos ni credenciales."""


def require(condition, path, message):
    if not condition:
        raise SnapshotError(f"{path}: {message}")


def fields(value, names, path):
    require(type(value) is dict, path, "se esperaba un objeto")
    require(set(value) == set(names.split()), path, "campos faltantes o no permitidos")


def sequence(value, path):
    require(type(value) is list and len(value) <= 500, path, "se esperaba una lista de hasta 500 registros")


def integer(value, path):
    require(type(value) is int and value > 0, path, "se esperaba un entero positivo")


def text_field(value, path, pattern=None):
    require(type(value) is str and 0 < len(value) <= 300, path, "texto vacío o demasiado largo")
    require(not any(ord(c) < 32 or ord(c) == 127 for c in value), path, "caracteres de control no permitidos")
    require(not SECRET.search(value), path, "posible credencial; quitarla de la captura")
    if pattern:
        require(pattern.fullmatch(value) is not None, path, "formato de texto no permitido")


def choice(value, options, path):
    require(type(value) is str and value in options.split(), path, "valor no permitido")


def timestamp(value, path):
    require(type(value) is str and re.fullmatch(r"\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ", value) is not None,
            path, "usar fecha UTC YYYY-MM-DDTHH:MM:SSZ")
    try:
        return datetime.strptime(value, "%Y-%m-%dT%H:%M:%SZ").replace(tzinfo=timezone.utc)
    except ValueError:
        raise SnapshotError(f"{path}: fecha UTC inválida") from None


def ids(value, path):
    sequence(value, path)
    for item in value:
        integer(item, path)
    require(len(set(value)) == len(value), path, "identificadores duplicados")


def evidence_url(value, repository, path):
    if value is None:
        return
    text_field(value, path)
    try:
        url = urlsplit(value)
        valid = (url.scheme == "https" and url.netloc == "github.com" and not url.query
                 and re.fullmatch(
                     re.escape("/" + repository) +
                     r"/(?:issues/\d+|pull/\d+(?:/reviews/\d+)?|actions/runs/\d+(?:/job/\d+)?|commit/[0-9a-f]{40})",
                     url.path,
                 ) is not None
                 and re.fullmatch(r"[A-Za-z0-9_-]*", url.fragment) is not None)
    except ValueError:
        valid = False
    require(valid, path, "usar URL GitHub del repositorio, sin credenciales ni query")


def unique_records(records, path):
    sequence(records, path)
    seen = set()
    for i, record in enumerate(records):
        location = f"{path}[{i}]"
        require(type(record) is dict and "number" in record, location, "falta número de registro")
        integer(record["number"], location + ".number")
        require(record["number"] not in seen, location, "número duplicado")
        seen.add(record["number"])


def validate_snapshot(data):
    """Valida estructura y campos seguros; contradicciones factuales van al informe."""
    fields(data, "schema_version repository source captured_at validation milestones issues pull_requests", "$")
    require(type(data["schema_version"]) is int and data["schema_version"] == 1,
            "$.schema_version", "versión no soportada")
    text_field(data["repository"], "$.repository", REPOSITORY)
    require(data["repository"].split("/")[1] not in (".", ".."), "$.repository", "repositorio inválido")
    choice(data["source"], "provided_snapshot controlled_fixture", "$.source")
    timestamp(data["captured_at"], "$.captured_at")
    fields(data["validation"], "by at", "$.validation")
    validation = data["validation"]
    require((validation["by"] is None) == (validation["at"] is None), "$.validation", "by/at deben declararse juntos")
    if validation["by"] is not None:
        text_field(validation["by"], "$.validation.by", LOGIN)
        timestamp(validation["at"], "$.validation.at")
    for name in ("milestones", "issues", "pull_requests"):
        unique_records(data[name], "$." + name)
    for i, record in enumerate(data["milestones"]):
        path = f"$.milestones[{i}]"
        fields(record, "number phase title state updated_at target_date academic_deadline_confirmed", path)
        choice(record["phase"], "M3 M4", path + ".phase")
        text_field(record["title"], path + ".title")
        choice(record["state"], "open closed", path + ".state")
        timestamp(record["updated_at"], path + ".updated_at")
        target = record["target_date"]
        if target is not None:
            require(type(target) is str and re.fullmatch(r"\d{4}-\d\d-\d\d", target) is not None,
                    path + ".target_date", "usar fecha YYYY-MM-DD o null")
            try:
                date.fromisoformat(target)
            except ValueError:
                raise SnapshotError(path + ".target_date: fecha inválida") from None
        require(record["academic_deadline_confirmed"] is None or type(record["academic_deadline_confirmed"]) is bool,
                path + ".academic_deadline_confirmed", "usar booleano o null")
    phases = [m["phase"] for m in data["milestones"]]
    require(len(set(phases)) == len(phases), "$.milestones", "fase duplicada")
    for i, record in enumerate(data["issues"]):
        path = f"$.issues[{i}]"
        fields(record, "number title milestone state updated_at dependencies pull_requests implementation engineer_acceptance", path)
        text_field(record["title"], path + ".title")
        if record["milestone"] is not None:
            integer(record["milestone"], path + ".milestone")
        choice(record["state"], "open closed", path + ".state")
        timestamp(record["updated_at"], path + ".updated_at")
        for name in ("dependencies", "pull_requests"):
            if record[name] is not None:
                ids(record[name], path + "." + name)
        choice(record["implementation"], "unknown proposal in_progress implemented", path + ".implementation")
        acceptance = record["engineer_acceptance"]
        fields(acceptance, "status by at evidence_url", path + ".engineer_acceptance")
        choice(acceptance["status"], "unknown pending accepted rejected", path + ".engineer_acceptance.status")
        if acceptance["by"] is not None:
            text_field(acceptance["by"], path + ".engineer_acceptance.by", LOGIN)
        if acceptance["at"] is not None:
            timestamp(acceptance["at"], path + ".engineer_acceptance.at")
        evidence_url(acceptance["evidence_url"], data["repository"], path + ".engineer_acceptance.evidence_url")
    for i, record in enumerate(data["pull_requests"]):
        path = f"$.pull_requests[{i}]"
        fields(record, "number title issues state merged draft head_sha updated_at merged_at reviews_complete reviews checks", path)
        text_field(record["title"], path + ".title")
        ids(record["issues"], path + ".issues")
        choice(record["state"], "open closed", path + ".state")
        for key in ("merged", "draft", "reviews_complete"):
            require(type(record[key]) is bool, path + "." + key, "se esperaba booleano")
        text_field(record["head_sha"], path + ".head_sha", SHA)
        timestamp(record["updated_at"], path + ".updated_at")
        if record["merged_at"] is not None:
            timestamp(record["merged_at"], path + ".merged_at")
        sequence(record["reviews"], path + ".reviews")
        seen = set()
        for j, review in enumerate(record["reviews"]):
            loc = f"{path}.reviews[{j}]"
            fields(review, "id by state commit_sha submitted_at", loc)
            integer(review["id"], loc + ".id")
            require(review["id"] not in seen, loc, "review duplicada")
            seen.add(review["id"])
            text_field(review["by"], loc + ".by", LOGIN)
            choice(review["state"], "APPROVED CHANGES_REQUESTED COMMENTED DISMISSED PENDING", loc + ".state")
            text_field(review["commit_sha"], loc + ".commit_sha", SHA)
            if review["submitted_at"] is not None:
                timestamp(review["submitted_at"], loc + ".submitted_at")
        fields(record["checks"], "status sha evidence_url", path + ".checks")
        choice(record["checks"]["status"], "unknown pending success failure", path + ".checks.status")
        if record["checks"]["sha"] is not None:
            text_field(record["checks"]["sha"], path + ".checks.sha", SHA)
        evidence_url(record["checks"]["evidence_url"], data["repository"], path + ".checks.evidence_url")
    return data


def _no_duplicate_keys(pairs):
    result = {}
    for key, value in pairs:
        require(key not in result, "$", "clave JSON duplicada")
        result[key] = value
    return result


def read_snapshot(path):
    with Path(path).open("rb") as stream:
        raw = stream.read(MAX_BYTES + 1)
    require(len(raw) <= MAX_BYTES, "$", "snapshot supera 5 MiB")
    try:
        data = json.loads(raw.decode("utf-8-sig"), object_pairs_hook=_no_duplicate_keys,
                          parse_constant=lambda _: require(False, "$", "constante JSON inválida"))
        validate_snapshot(data)
    except SnapshotError:
        raise
    except (ValueError, RecursionError):
        raise SnapshotError("$: JSON UTF-8 inválido") from None
    return data, hashlib.sha256(raw).hexdigest()


def pr_observation(pr, captured):
    gaps = []
    if timestamp(pr["updated_at"], "$updated_at") > captured:
        gaps.append("record_after_capture")
    if pr["merged_at"] and timestamp(pr["merged_at"], "$merged_at") > captured:
        gaps.append("merge_after_capture")
    if pr["merged"] and pr["state"] == "closed" and pr["merged_at"] and not pr["draft"]:
        merge = "merged_reported"
    elif pr["merged"] or pr["merged_at"]:
        merge = "contradictory"
        gaps.append("merge_metadata_contradiction")
    else:
        merge = "open" if pr["state"] == "open" else "closed_unmerged"
    if merge != "merged_reported":
        gaps.append("merge_not_confirmed")
    if pr["draft"]:
        gaps.append("draft_pull_request")
    latest = {}
    undated = False
    future_review = False
    for review in sorted(pr["reviews"], key=lambda r: (r["submitted_at"] or "", r["id"])):
        if review["submitted_at"] and timestamp(review["submitted_at"], "$submitted_at") > captured:
            future_review = True
        if review["state"] in ("APPROVED", "CHANGES_REQUESTED", "DISMISSED"):
            if review["submitted_at"] is None:
                undated = True
            else:
                latest[review["by"].casefold()] = review
    current = [r["by"] for r in latest.values()
               if r["state"] == "APPROVED" and r["commit_sha"] == pr["head_sha"]]
    outdated = [r["by"] for r in latest.values()
                if r["state"] == "APPROVED" and r["commit_sha"] != pr["head_sha"]]
    changes = [r["by"] for r in latest.values() if r["state"] == "CHANGES_REQUESTED"]
    if future_review:
        gaps.append("review_after_capture")
    if not pr["reviews_complete"] or undated or future_review:
        review_status = "unknown"
        gaps.append("review_history_incomplete")
    elif changes:
        review_status = "changes_requested"
        gaps.append("changes_requested")
    elif current:
        review_status = "current_sha_approval_reported"
    else:
        review_status = "outdated_approval" if outdated else "no_current_approval"
        gaps.append("current_sha_approval_missing")
    checks = pr["checks"]
    check_status = checks["status"]
    if check_status != "unknown" and (checks["sha"] != pr["head_sha"] or not checks["evidence_url"]):
        check_status = "unknown"
        gaps.append("checks_evidence_missing_or_other_sha")
    if check_status != "success":
        gaps.append("checks_not_confirmed_success")
    return {
        "number": pr["number"], "head_sha": pr["head_sha"], "state_reported": pr["state"],
        "merged_at_reported": pr["merged_at"], "reviews_reported": pr["reviews"],
        "reviews_complete_reported": pr["reviews_complete"],
        "merged_reported": pr["merged"], "merge_observation": merge,
        "review_observation": review_status, "current_sha_approvals_reported": sorted(current),
        "outdated_approvals_reported": sorted(outdated), "changes_requested_by": sorted(changes),
        "checks_reported": checks, "checks_observation": check_status, "pending_codes": gaps,
    }


def build_report(data, digest, now=None, max_age_hours=24):
    validate_snapshot(data)
    now = now or datetime.now(timezone.utc)
    captured = timestamp(data["captured_at"], "$.captured_at")
    require(math.isfinite(max_age_hours) and 0 < max_age_hours <= 8760,
            "$", "antigüedad máxima debe estar entre 0 (exclusivo) y 8760 horas")
    findings = []

    def finding(code, message, issue=None, pr=None, category="technical"):
        findings.append({"code": code, "category": category, "message": message, "issue": issue, "pull_request": pr})

    future = captured > now
    stale = now - captured > timedelta(hours=max_age_hours)
    freshness = "future" if future else "stale" if stale else "within_limit"
    if freshness != "within_limit":
        finding("snapshot_" + freshness, "Actualizar la captura: fecha futura o antigüedad fuera del límite.", category="snapshot")
    if data["source"] == "controlled_fixture":
        finding("controlled_fixture", "Datos sintéticos de prueba; no son evidencia real de GitHub.", category="snapshot")
    validation = data["validation"]
    validation_valid = bool(validation["by"] and captured <= timestamp(validation["at"], "$.validation.at") <= now)
    if not validation_valid:
        finding("snapshot_validation_missing_or_invalid", "Falta validación declarada o sus fechas son incoherentes.", category="snapshot")
    milestones = {m["number"]: m for m in data["milestones"]}
    for phase in ("M3", "M4"):
        if not any(m["phase"] == phase for m in milestones.values()):
            finding("milestone_missing", "Falta metadata del milestone " + phase + ".")
    issues = {i["number"]: i for i in data["issues"]}
    pulls = {p["number"]: p for p in data["pull_requests"]}
    observations = {n: pr_observation(p, captured) for n, p in pulls.items()}
    dependencies = {n: sorted(set(i["dependencies"] or []) | set(EXPECTED_DEPENDENCIES.get(n, ())))
                    for n, i in issues.items()}
    required_issues = set(EXPECTED_PHASES) | {47}
    required_issues.update(d for deps in dependencies.values() for d in deps)
    required_issues.update(i for pr in pulls.values() for i in pr["issues"])
    missing = sorted(required_issues - issues.keys())
    for n in missing:
        finding("issue_missing", "Falta el registro de la tarea; su estado es desconocido.", issue=n)
    for n, issue in EXPECTED_PULLS.items():
        if n not in pulls:
            finding("pull_request_missing", "Falta metadata del PR de la línea base.", issue=issue, pr=n)
        elif issue not in pulls[n]["issues"] or n not in (issues.get(issue, {}).get("pull_requests") or []):
            finding("baseline_pr_link_missing", "Falta asociación recíproca entre tarea y PR de la línea base.", issue=issue, pr=n)
    for milestone in data["milestones"]:
        if timestamp(milestone["updated_at"], "$updated_at") > captured:
            finding("record_after_capture", "Milestone actualizado después de la captura; recapturar.")
        if milestone["academic_deadline_confirmed"] and not milestone["target_date"]:
            finding("academic_deadline_without_date", "Se declara fecha académica confirmada sin aportar fecha.")
    for n, pr in pulls.items():
        for code in observations[n]["pending_codes"]:
            finding(code, "PR con evidencia pendiente: " + code + ".", pr=n)
        for issue in pr["issues"]:
            if issue not in issues or n not in (issues[issue]["pull_requests"] or []):
                finding("pr_issue_link_inconsistent", "Asociación de PR/tarea ausente o no recíproca.", issue=issue, pr=n)
    rows = {}
    for n, issue in issues.items():
        codes = []
        technical_codes = []
        delivery_codes = []

        def gap(code, message, delivery_warning=False):
            if code not in codes:
                codes.append(code)
                (delivery_codes if delivery_warning else technical_codes).append(code)
                finding(code, message, issue=n, category="delivery_warning" if delivery_warning else "technical")

        reported_phase = milestones.get(issue["milestone"], {}).get("phase")
        expected_phase = EXPECTED_PHASES.get(n)
        if reported_phase is None:
            gap("milestone_unknown", "Milestone ausente o no capturado; no reasignar la tarea.")
        elif expected_phase and reported_phase != expected_phase:
            gap("milestone_mismatch", "Milestone distinto de la línea base; conservar M3 y M4 separados.")
        if issue["state"] == "open":
            gap("issue_open", "La tarea sigue abierta según el snapshot.")
        if timestamp(issue["updated_at"], "$updated_at") > captured:
            gap("record_after_capture", "Registro actualizado después de la captura; recapturar.")
        if issue["implementation"] != "implemented":
            gap("implementation_not_confirmed", "No hay implementación declarada completa.")
        acceptance = issue["engineer_acceptance"]
        accepted = (acceptance["status"] == "accepted" and acceptance["by"] and acceptance["at"]
                    and acceptance["evidence_url"] and timestamp(acceptance["at"], "$acceptance.at") <= captured)
        if not accepted:
            gap("engineer_acceptance_not_confirmed", "Sin aceptación individual declarada del ingeniero; dato de entrega, no bloqueo técnico.",
                delivery_warning=True)
        if issue["state"] == "closed" and (issue["implementation"] != "implemented" or not accepted):
            gap("closed_issue_is_not_delivery", "Cerrar una issue no acredita aceptación académica del corte main.",
                delivery_warning=True)
        if issue["dependencies"] is None:
            gap("dependencies_unknown", "La lista de dependencias no fue capturada completamente.")
        elif set(EXPECTED_DEPENDENCIES.get(n, ())) - set(issue["dependencies"]):
            gap("baseline_dependencies_omitted", "Faltan dependencias de la línea base; se conservan en el análisis.")
        links = issue["pull_requests"]
        if not links:
            gap("pull_request_evidence_missing", "No hay lista de PR con evidencia suficiente; no inferir integración.")
        for pr in links or []:
            if pr not in pulls:
                gap("pull_request_missing", "Falta metadata de un PR asociado.")
            elif n not in pulls[pr]["issues"]:
                gap("issue_pr_link_inconsistent", "El PR no declara asociación recíproca con la tarea.")
            elif observations[pr]["pending_codes"]:
                gap("pull_request_pending", "PR asociado con integración, revisión o checks pendientes.")
        rows[n] = {
            "number": n, "title": issue["title"], "group": expected_phase or reported_phase or "unassigned",
            "milestone_reported": issue["milestone"], "phase_reported": reported_phase,
            "state_reported": issue["state"], "implementation_reported": issue["implementation"],
            "engineer_acceptance_reported": acceptance, "pull_requests": links,
            "dependencies_reported": issue["dependencies"], "dependencies_considered": dependencies[n],
            "context_note_2026_10_31": CONTEXT_NOTES_20261031.get(n), "pending_codes": codes,
            "technical_pending_codes": technical_codes, "delivery_warning_codes": delivery_codes,
        }
    # DFS identifica ciclos sin interpretar una issue cerrada como dependencia resuelta.
    visited, active = set(), []

    def visit(n):
        if n in active:
            for cyclic in active[active.index(n):]:
                if "dependency_cycle" not in rows[cyclic]["pending_codes"]:
                    rows[cyclic]["pending_codes"].append("dependency_cycle")
                    rows[cyclic]["technical_pending_codes"].append("dependency_cycle")
                    finding("dependency_cycle", "Ciclo de dependencias; requiere revisión humana.", issue=cyclic)
            return
        if n in visited or n not in rows:
            return
        active.append(n)
        for dep in dependencies[n]:
            visit(dep)
        active.pop()
        visited.add(n)

    for n in rows:
        visit(n)
    context_uncertain = freshness != "within_limit" or not validation_valid or data["source"] == "controlled_fixture"
    unresolved = set(missing) | {n for n, row in rows.items() if row["technical_pending_codes"] or context_uncertain}
    changed = True
    while changed:
        before = len(unresolved)
        unresolved.update(n for n, deps in dependencies.items() if any(d in unresolved for d in deps))
        changed = len(unresolved) != before
    for n, row in rows.items():
        row["unresolved_dependencies"] = [d for d in dependencies[n] if d in unresolved]
        row["dependency_assessment"] = ("unknown_snapshot" if context_uncertain else
                                        "pending" if row["unresolved_dependencies"] else "no_local_gaps_detected")
        if row["unresolved_dependencies"]:
            row["pending_codes"].append("dependency_unresolved")
            row["technical_pending_codes"].append("dependency_unresolved")
            finding("dependency_unresolved", "Dependencias ausentes o con evidencia pendiente.", issue=n)
    return {
        "report_version": 1, "purpose": "PREPARACION_118", "delivery_status": "not_evaluated",
        "repository": data["repository"], "source": data["source"], "snapshot_sha256": digest,
        "captured_at": data["captured_at"], "evaluated_at": now.strftime("%Y-%m-%dT%H:%M:%SZ"),
        "max_age_hours": max_age_hours, "freshness": freshness, "validation_reported": validation,
        "notice": "Observaciones declaradas en un snapshot; no verificadas contra GitHub. No certifica M4, CI, aprobación ni entrega.",
        "scope_constraints": ["Este informe no certifica producto de agua ni entrega final.",
                              "M3 no se incorpora como trabajo terminado de M4.",
                              "No se infiere cronograma ni capacidad.", "Sin PDF ni paquete final."],
        "context_as_of": "2026-10-31",
        "main_context_2026_10_31": "M2 según el contexto comunicado; no determina el estado de main en capturas posteriores.",
        "milestones_reported": data["milestones"], "missing_issues": missing,
        "groups": {phase: [rows[n] for n in sorted(rows) if rows[n]["group"] == phase]
                   for phase in ("M3", "M4", "unassigned")},
        "pull_requests": [observations[n] for n in sorted(observations)],
        "pending": findings, "pending_count": len(findings),
    }


def markdown_index(report):
    def safe(value):
        return re.sub(r"([\\`*_[\]{}|])", r"\\\1", html.escape(str(value), quote=True))

    base = "https://github.com/" + report["repository"]
    lines = ["# PREPARACIÓN #118 — índice de evidencias M4", "", report["notice"], "",
             f"- Fuente: `{report['source']}`.",
             f"- Captura UTC: {report['captured_at']}; evaluación UTC: {report['evaluated_at']}.",
             f"- Vigencia: `{report['freshness']}` (máximo {report['max_age_hours']} horas).",
             f"- SHA-256 del snapshot recibido: `{report['snapshot_sha256']}`.",
             "- Resumen legible por herramientas: [resumen.json](resumen.json).", ""]
    lines.extend("- " + note for note in report["scope_constraints"])
    lines.extend(["", "Contexto comunicado al 31/10/2026: " + report["main_context_2026_10_31"],
                  "Las notas fechadas de tareas son antecedentes, no estados actuales de una captura posterior."])
    if report["source"] == "controlled_fixture":
        lines.extend(["", "**FIXTURE CONTROLADA: datos sintéticos; no son evidencia real.**"])
    lines.extend(["", "## Metas de milestones declaradas", ""])
    for milestone in report["milestones_reported"]:
        academic = {True: "confirmada según captura", False: "no confirmada", None: "desconocida"}[milestone["academic_deadline_confirmed"]]
        lines.append(f"- {milestone['phase']}, milestone #{milestone['number']}: meta `{milestone['target_date'] or 'desconocida'}`; "
                     f"fecha académica {academic}. No se calcula cronograma.")
    for group, title in (("M3", "M3 — antecedentes y dependencias, fuera de la entrega M4"),
                         ("M4", "M4 — preparación y pendientes"), ("unassigned", "Sin milestone confirmado")):
        lines.extend(["", "## " + title, ""])
        if not report["groups"][group]:
            lines.append("Sin registros capturados; no implica ausencia de pendientes.")
        for row in report["groups"][group]:
            n = row["number"]
            lines.extend([f"### [#{n}]({base}/issues/{n}) — {safe(row['title'])}", "",
                          f"- Estado declarado: `{row['state_reported']}`; implementación: `{row['implementation_reported']}`.",
                          f"- Aceptación del ingeniero declarada: `{row['engineer_acceptance_reported']['status']}`; no verificada aquí.",
                          f"- Milestone declarado: {row['milestone_reported']}; fase declarada: {row['phase_reported']}."])
            if row["context_note_2026_10_31"]:
                lines.append("- Contexto comunicado al 31/10/2026: " + row["context_note_2026_10_31"])
            deps = ", ".join(f"[#{d}]({base}/issues/{d})" for d in row["dependencies_considered"])
            lines.append("- Dependencias consideradas: " + (deps or ("desconocidas" if row["dependencies_reported"] is None else "ninguna declarada")) + ".")
            lines.append(f"- Evaluación de dependencias: `{row['dependency_assessment']}`.")
            lines.append("- Dependencias pendientes: " + (", ".join(f"#{d}" for d in row["unresolved_dependencies"]) or "sin brechas locales detectadas; no acredita entrega") + ".")
            prs = ", ".join(f"[#{p}]({base}/pull/{p})" for p in row["pull_requests"] or [])
            lines.append("- PR declarados: " + (prs or "sin evidencia") + ".")
            lines.append("- Pendientes técnicos: " + (", ".join(f"`{c}`" for c in row["technical_pending_codes"]) or "sin brechas locales detectadas; no acredita entrega") + ".")
            lines.append("- Advertencias independientes de entrega: " + (", ".join(f"`{c}`" for c in row["delivery_warning_codes"]) or "sin advertencias individuales; la aceptación del corte main no se evalúa") + ".")
            lines.append("")
    lines.extend(["## PR, revisión y checks declarados", ""])
    for pr in report["pull_requests"]:
        n = pr["number"]
        lines.extend([f"### [PR #{n}]({base}/pull/{n})", "",
                      f"- HEAD: `{pr['head_sha']}`; fusión: `{pr['merge_observation']}`.",
                      f"- Revisión: `{pr['review_observation']}`; checks: `{pr['checks_observation']}`.",
                      "- Aprobar otro SHA no aprueba HEAD. Los checks proceden solo del snapshot; no se consultaron por CLI."])
        for review in pr["reviews_reported"]:
            lines.append(f"- Review {review['id']}, {safe(review['by'])}: `{review['state']}` sobre `{review['commit_sha']}`; {review['submitted_at']}.")
        lines.append("")
    lines.extend(["## Datos faltantes y observaciones pendientes", ""])
    for item in report["pending"]:
        ref = f" #{item['issue']}" if item["issue"] else ""
        ref += f" PR #{item['pull_request']}" if item["pull_request"] else ""
        lines.append(f"- `{item['code']}` ({item['category']}){ref}: {safe(item['message'])}")
    if not report["pending"]:
        lines.append("Sin brechas detectadas en los campos capturados. La entrega final sigue sin evaluarse.")
    return "\n".join(lines) + "\n"


def _rename_without_replacement(source, destination):
    """Publicación atómica que tampoco reemplaza un directorio vacío en una carrera."""
    if sys.platform == "win32":
        for attempt in range(4):
            try:
                os.rename(source, destination)  # Windows falla si el destino ya existe.
                return
            except PermissionError as exc:
                # Lectores/antivirus pueden retener brevemente un handle sin FILE_SHARE_DELETE.
                if getattr(exc, "winerror", None) not in (5, 32, 33) or os.path.lexists(destination) or attempt == 3:
                    raise
                time.sleep(0.05 * (attempt + 1))
    elif sys.platform.startswith("linux"):
        libc = ctypes.CDLL(None, use_errno=True)
        rename = getattr(libc, "renameat2", None)
        if rename is None:
            raise OSError(errno.ENOTSUP, "renameat2 no disponible")
        rename.argtypes = [ctypes.c_int, ctypes.c_char_p, ctypes.c_int, ctypes.c_char_p, ctypes.c_uint]
        rename.restype = ctypes.c_int
        if rename(-100, os.fsencode(source), -100, os.fsencode(destination), 1):
            error = ctypes.get_errno()
            raise OSError(error, "no se pudo publicar sin reemplazo")
    else:
        raise OSError(errno.ENOTSUP, "publicación atómica soportada en Windows y Linux")


def publish_report(report, destination):
    destination = Path(destination).absolute()
    parent = destination.parent.resolve(strict=True)
    target = parent / destination.name
    if os.path.lexists(target):
        raise FileExistsError("el destino ya existe")
    staging = Path(tempfile.mkdtemp(prefix=".preparacion-118-staging-", dir=parent))
    try:
        for name, content in (("indice.md", markdown_index(report)),
                              ("resumen.json", json.dumps(report, ensure_ascii=False, indent=2, allow_nan=False) + "\n")):
            with (staging / name).open("x", encoding="utf-8", newline="\n") as stream:
                stream.write(content)
        _rename_without_replacement(staging, target)
    finally:
        # Solo se elimina el staging creado por este proceso, nunca el destino.
        if staging.exists():
            if staging.is_symlink() or staging.resolve().parent != parent:
                raise OSError("staging fuera del padre de publicación; no se elimina")
            shutil.rmtree(staging)
    return target


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--snapshot", required=True, type=Path, help="snapshot.json suministrado; no se consulta GitHub")
    parser.add_argument("--output", required=True, type=Path, help="directorio nuevo; su padre debe existir")
    parser.add_argument("--max-age-hours", type=float, default=24, help="máxima antigüedad admitida, por defecto 24 h")
    parser.add_argument("--fixture-now", help="reloj fijo UTC, permitido únicamente para controlled_fixture")
    parser.add_argument("--fail-on-pending", action="store_true", help="salir con 3 después de publicar si hay pendientes")
    args = parser.parse_args(argv)
    try:
        data, digest = read_snapshot(args.snapshot)
        require(not args.fixture_now or data["source"] == "controlled_fixture", "$", "reloj fijo solo permitido para fixtures")
        now = timestamp(args.fixture_now, "--fixture-now") if args.fixture_now else None
        report = build_report(data, digest, now, args.max_age_hours)
        publish_report(report, args.output)
    except SnapshotError as exc:
        print("Error de snapshot: " + str(exc), file=sys.stderr)
        return 2
    except OSError:
        print("Error de archivos: revisar entrada, padre existente, destino nuevo y permisos; no se reemplazó una salida anterior.", file=sys.stderr)
        return 2
    print(f"PREPARACION #118 generada; {report['pending_count']} observaciones pendientes. Entrega M4 no evaluada.")
    return 3 if args.fail_on_pending and report["pending"] else 0


if __name__ == "__main__":
    raise SystemExit(main())
