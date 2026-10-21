"""Contrasta resultados observados de web y Flutter sobre un replay común."""
import argparse
import datetime
import hashlib
import json
from pathlib import Path

root = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--directorio', default=str(root / 'tmp/qa21'))
args = parser.parse_args()
directory = Path(args.directorio)
web = json.loads((directory / 'web.json').read_text(encoding='utf-8'))
mobile = json.loads((directory / 'movil.json').read_text(encoding='utf-8'))
cases = {'equivalencia', 'paginacion', 'vacio', 'sin_cobertura', 'parcial',
         'fallo_reintento', 'expiracion', 'respuesta_antigua', 'periodo_invalido',
         'territorio_incompatible', 'cero_faltante'}
for client in (web, mobile):
    if client['origen'] != 'replay_controlado' or set(client['casos']) != cases or len(client['casos']) != len(cases):
        raise SystemExit('No pasaron todos los casos esperados: ' + client['cliente'])
if web['equivalencia'] != mobile['equivalencia']:
    raise SystemExit('Web y Flutter no conservan los mismos datos/contexto')
capture = root / 'docs/evidencias/api01/consulta_real.json'
report = {
    'fechaUtc': datetime.datetime.now(datetime.timezone.utc).isoformat(),
    'resultado': 'correcto', 'tipo': 'replay_controlado',
    'casosPorCliente': len(cases), 'registrosComparados': len(web['equivalencia']['datos']),
    'camposComparados': ['IDs', 'coordenadas', 'atributos/unidades', 'fechas', 'territorio',
                       'faltantes', 'naturaleza', 'método', 'fuentes', 'consulta',
                       'resumen', 'cobertura', 'advertencias', 'paginación/versionConjunto'],
    'capturaHistorica': str(capture.relative_to(root)).replace('\\', '/'),
    'sha256Captura': hashlib.sha256(capture.read_bytes()).hexdigest(),
    'casos': sorted(cases),
    'limite': 'Reproduce datos capturados y casos derivados. No ejecuta UI en emulador ni una nueva consulta NASA.',
}
(directory / 'comparacion.json').write_text(json.dumps(report, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
print(json.dumps(report, ensure_ascii=False, indent=2))
