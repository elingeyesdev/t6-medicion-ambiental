"""Extrae la referencia XML del Planner; no recalcula fechas ni escribe en ProjectLibre."""
import argparse
from datetime import datetime
from decimal import Decimal
import hashlib
import json
from pathlib import Path
import re
import xml.etree.ElementTree as ET

NS = {"p": "http://schemas.microsoft.com/project"}
DURATION = re.compile(r"PT(\d+)H(\d+)M(\d+)S\Z")


def minutos(value):
    match = DURATION.fullmatch(value or "")
    if not match or int(match[2]) >= 60 or int(match[3]) >= 60:
        raise ValueError("Duración de intercambio no admitida.")
    return Decimal(match[1]) * 60 + Decimal(match[2]) + Decimal(match[3]) / 60


def extraer(raw):
    if not 0 < len(raw) <= 4 * 1024 * 1024 or b"<!DOCTYPE" in raw.upper() or b"<!ENTITY" in raw.upper():
        raise ValueError("XML fuera del formato permitido.")
    root = ET.fromstring(raw)
    if root.tag != "{" + NS["p"] + "}Project":
        raise ValueError("No es un intercambio Project.")
    def field(element, name, default=None):
        return element.findtext("p:" + name, default=default, namespaces=NS)
    day = int(field(root, "MinutesPerDay"))
    if not 0 < day <= 1440:
        raise ValueError("Jornada técnica no válida.")
    resources = {field(r, "UID"): field(r, "Name") for r in root.findall("p:Resources/p:Resource", NS)}
    tasks, seen = {}, set()
    for task in root.findall("p:Tasks/p:Task", NS):
        uid = field(task, "UID")
        if uid is None or not uid.isdecimal() or uid in seen:
            raise ValueError("UID de tarea ausente o duplicado.")
        seen.add(uid)
        start, finish = field(task, "Start"), field(task, "Finish")
        if datetime.fromisoformat(start) > datetime.fromisoformat(finish):
            raise ValueError("Fechas de referencia invertidas.")
        duration = minutos(field(task, "Duration"))
        dependencies = []
        for link in task.findall("p:PredecessorLink", NS):
            if field(link, "Type") != "1" or field(link, "LinkLag", "0") != "0":
                raise ValueError("Este lector solo contrasta enlaces fin-inicio sin desfase.")
            dependencies.append(field(link, "PredecessorUID"))
        if len(set(dependencies)) != len(dependencies):
            raise ValueError("Predecesora duplicada.")
        tasks[uid] = {
            "uid": int(uid), "nombre": field(task, "Name"), "inicioReferencia": start,
            "finReferencia": finish, "duracionMinutos": str(duration),
            "trabajoMinutos": str(minutos(field(task, "Work", "PT0H0M0S"))),
            "resumen": field(task, "Summary") == "1", "hito": field(task, "Milestone") == "1",
            "porcentajeEnArchivo": field(task, "PercentComplete"),
            "prioridadEnArchivo": field(task, "Priority"),
            "predecesorasUid": [int(p) for p in dependencies], "recursos": [],
        }
    warnings = []
    for task in tasks.values():
        for uid in task["predecesorasUid"]:
            if str(uid) not in tasks:
                raise ValueError("Falta una predecesora.")
            previous = tasks[str(uid)]
            if datetime.fromisoformat(task["inicioReferencia"]) < datetime.fromisoformat(previous["finReferencia"]):
                warnings.append({"tipo": "fecha_exportada_antes_de_predecesora", "tareaUid": task["uid"],
                                 "predecesoraUid": uid, "inicioExportado": task["inicioReferencia"],
                                 "finPredecesoraExportado": previous["finReferencia"]})
    for assignment in root.findall("p:Assignments/p:Assignment", NS):
        uid, person = field(assignment, "TaskUID"), field(assignment, "ResourceUID")
        if uid not in tasks or person not in resources:
            raise ValueError("Asignación a tarea o recurso ausente.")
        tasks[uid]["recursos"].append({"nombre": resources[person], "unidades": field(assignment, "Units"),
                                      "trabajoMinutos": str(minutos(field(assignment, "Work")))})
    activities = {}
    for task in tasks.values():
        match = re.match(r"^([A-L]) - ", task["nombre"] or "")
        if match and not task["resumen"] and not task["hito"]:
            if match[1] in activities:
                raise ValueError("Actividad duplicada.")
            activities[match[1]] = task
    if set(activities) != set("ABCDEFGHIJKL"):
        raise ValueError("Se necesitan las doce actividades A–L de la referencia.")
    by_uid = {v["uid"]: k for k, v in activities.items()}
    early, stack = {}, set()
    def forward(letter):
        if letter in stack:
            raise ValueError("Ciclo de precedencias.")
        if letter in early:
            return early[letter][1]
        stack.add(letter)
        task = activities[letter]
        if any(p not in by_uid for p in task["predecesorasUid"]):
            raise ValueError("La red A–L tiene una dependencia ajena.")
        start = max((forward(by_uid[p]) for p in task["predecesorasUid"]), default=Decimal(0))
        early[letter] = start, start + Decimal(task["duracionMinutos"])
        stack.remove(letter)
        return early[letter][1]
    for letter in activities:
        forward(letter)
    network = max(x[1] for x in early.values())
    successors = {k: [] for k in activities}
    for letter, task in activities.items():
        for uid in task["predecesorasUid"]:
            successors[by_uid[uid]].append(letter)
    late = {}
    def backward(letter):
        if letter not in late:
            finish = min((backward(s) for s in successors[letter]), default=network)
            late[letter] = finish - Decimal(activities[letter]["duracionMinutos"])
        return late[letter]
    for letter in activities:
        backward(letter)
    rows = []
    for letter in sorted(activities):
        task = activities[letter]
        rows.append({"actividad": letter, **task,
                     "duracionJornadasReferencia": str(Decimal(task["duracionMinutos"]) / day),
                     "predecesorasActividad": [by_uid[p] for p in task["predecesorasUid"]],
                     "holguraJornadasRedSinNivelar": str((late[letter] - early[letter][0]) / day)})
    return {
        "tipo": "referencia_historica_sin_pronostico_nuevo", "sha256Xml": hashlib.sha256(raw).hexdigest(),
        "nombreEnArchivo": field(root, "Name"), "inicioEnArchivo": field(root, "StartDate"),
        "finEnArchivo": field(root, "FinishDate"), "minutosPorJornadaTecnica": day,
        "disponibilidadActualConfirmada": False, "pronosticoActual": None,
        "jornadasRedSinNivelar": str(network / day),
        "actividadesHolguraCero": [k for k in sorted(activities) if late[k] == early[k][0]],
        "actividades": rows, "otrasTareas": [t for t in tasks.values() if t["uid"] not in by_uid],
        "advertenciasExportacion": warnings,
        "nota": "Duración, trabajo y jornada son campos distintos. Los porcentajes y fechas de este XML "
                "son históricos, no el avance real. La red ignora nivelación de recursos, nuevos alcances, "
                "ausencias y feriados. No fija otra fecha ni acredita aprobación académica.",
    }


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--xml", type=Path, required=True)
    parser.add_argument("--salida", type=Path, required=True)
    args = parser.parse_args()
    try:
        result = extraer(args.xml.read_bytes())
        with args.salida.open("x", encoding="utf-8") as output:
            output.write(json.dumps(result, ensure_ascii=False, indent=2) + "\n")
        print(json.dumps({"actividades": len(result["actividades"]), "jornadasRedReferencia": result["jornadasRedSinNivelar"],
                          "advertenciasExportacion": result["advertenciasExportacion"], "pronosticoActual": None}))
    except (ValueError, KeyError, TypeError, ET.ParseError, OSError):
        parser.exit(1, "No se pudo extraer la referencia; revisar XML y usar un archivo de salida nuevo.\n")


if __name__ == "__main__":
    main()
