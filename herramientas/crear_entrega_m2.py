"""Genera un PDF de revisión de M2 con evidencia original y resultados ejecutados."""
import argparse
import json
from pathlib import Path
from xml.sax.saxutils import escape

from reportlab.lib import colors
from reportlab.lib.enums import TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import (SimpleDocTemplate, Paragraph, Spacer, PageBreak,
                               Image, Table, TableStyle, KeepTogether)
from PIL import Image as PILImage

ROOT = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--salida', default=str(ROOT / 'output/pdf/GeoCruz_M2.pdf'))
args = parser.parse_args()
OUTPUT = Path(args.salida)
OUTPUT.parent.mkdir(parents=True, exist_ok=True)
matrix = json.loads((ROOT / 'docs/qa21/matriz.json').read_text(encoding='utf-8'))
cut = json.loads((ROOT / 'docs/entrega-m2/cierre_tecnico_20261021.json').read_text(encoding='utf-8'))
assert cut['qa21Integrado'] is True, 'Generar únicamente después de verificar la integración QA'
font_dir = Path('C:/Windows/Fonts')
pdfmetrics.registerFont(TTFont('ArialGC', str(font_dir / 'arial.ttf')))
pdfmetrics.registerFont(TTFont('ArialGC-Bold', str(font_dir / 'arialbd.ttf')))
pdfmetrics.registerFontFamily('ArialGC', normal='ArialGC', bold='ArialGC-Bold')
GREEN = colors.HexColor('#174F40')
INK = colors.HexColor('#18332C')
MUTED = colors.HexColor('#53675F')
LIGHT = colors.HexColor('#EFF5F1')
styles = getSampleStyleSheet()
styles.add(ParagraphStyle(name='GCBody', fontName='ArialGC', fontSize=10, leading=14.4,
                          textColor=INK, spaceAfter=9))
styles.add(ParagraphStyle(name='GCLead', parent=styles['GCBody'], fontSize=12, leading=18))
styles.add(ParagraphStyle(name='GCTitle', fontName='ArialGC-Bold', fontSize=30, leading=35,
                          textColor=GREEN, spaceAfter=17))
styles.add(ParagraphStyle(name='GCH1', fontName='ArialGC-Bold', fontSize=20, leading=25,
                          textColor=GREEN, spaceAfter=14))
styles.add(ParagraphStyle(name='GCH2', fontName='ArialGC-Bold', fontSize=12, leading=16,
                          textColor=GREEN, spaceBefore=10, spaceAfter=7))
styles.add(ParagraphStyle(name='GCSmall', parent=styles['GCBody'], fontSize=8.5, leading=11.5,
                          textColor=MUTED, spaceAfter=7))
styles.add(ParagraphStyle(name='GCCell', parent=styles['GCBody'], fontSize=8.5, leading=11,
                          spaceAfter=0))


def p(text, style='GCBody'):
    return Paragraph(text, styles[style])


def link(label, url):
    return f'<link href="{escape(url)}" color="#176B53">{escape(label)}</link>'


def picture(filename, width, maximum_height):
    path = ROOT / 'docs/QA-01 - Verificar integración web móvil y fuente de focos de calor' / filename
    with PILImage.open(path) as im:
        w, h = im.size
    ratio = min(width / w, maximum_height / h)
    return Image(str(path), width=w * ratio, height=h * ratio)


def footer(canvas, doc):
    canvas.saveState()
    canvas.setStrokeColor(colors.HexColor('#D8E4DC'))
    canvas.line(40, 43, A4[0] - 40, 43)
    canvas.setFillColor(MUTED)
    canvas.setFont('ArialGC', 8)
    canvas.drawString(40, 29, 'GeoCruz | M2 | Corte técnico 21/10/2026 | Revisión de entrega pendiente')
    canvas.drawRightString(A4[0] - 40, 29, str(doc.page))
    canvas.restoreState()


story = []
story += [Spacer(1, 24), p('UNIVALLE / SISTEMAS III', 'GCSmall'),
          p('GeoCruz<br/>Segunda iteración', 'GCTitle'),
          p('Focos de calor en web y móvil', 'GCLead'),
          p('Entrega prevista: 22 de octubre de 2026. Corte de preparación: 21 de octubre de 2026.'),
          Spacer(1, 10), p('<b>Josué Padilla</b> - Web, API y consolidación.<br/><b>Jaziel Díaz</b> - Flutter e integración.'),
          p('Qué se presenta', 'GCH2'),
          p('Consulta compartida de detecciones térmicas para Santa Cruz, con selección territorial, período, confianza, mapa, listado, detalle y resumen. Las interfaces conservan fuente, fechas, unidades y límites de interpretación.'),
          p('Estado de la entrega', 'GCH2'),
          p('<b>Implementación y QA integrados en develop.</b> El paquete ENT-02 / #22 queda preparado para revisión de Jaziel. La aceptación final de M2 y la aprobación académica siguen pendientes. Main no se modifica.'),
          p('Alcance que continúa', 'GCH2'),
          p('Focos de calor está disponible en web y Flutter. Sequías, inundaciones, deforestación y calidad del aire conservan sus accesos y requisitos, con consultas todavía pendientes. No se presentan los cinco módulos como implementados.'),
          p('<b>Interpretación:</b> una detección térmica no confirma un incendio. La confianza no representa gravedad; FRP en MW no es superficie quemada.'),
          p(link('Repositorio oficial', 'https://github.com/elingeyesdev/t6-medicion-ambiental') + ' | ' + link('Milestone M2', 'https://github.com/elingeyesdev/t6-medicion-ambiental/milestone/2'), 'GCSmall'),
          PageBreak()]

story += [p('Resultado de calidad', 'GCH1'),
          p('<b>11 casos ejecutados por cliente.</b> Se compararon 25 registros de una misma página entre salidas independientes de web y Flutter: IDs, coordenadas, atributos/unidades, fechas, territorio, faltantes, fuentes, naturaleza, método, contexto, resumen, cobertura y paginación.'),
          p('La comparación usa un replay controlado de una respuesta histórica de API. No representa una nueva descarga NASA ni un recorrido de todos los casos en emulador.'),
          p(link('CI reproducible de QA-01', matrix['ci']), 'GCSmall')]
labels = {
    'equivalencia': 'Equivalencia de datos', 'paginacion': 'Paginación / versión',
    'vacio': 'Vacío válido', 'sin_cobertura': 'Falta de cobertura', 'parcial': 'Cobertura parcial',
    'fallo_reintento': 'Fallo / reintento', 'expiracion': 'Expiración 409',
    'respuesta_antigua': 'Respuesta atrasada', 'periodo_invalido': 'Período inválido',
    'territorio_incompatible': 'Territorio incompatible', 'cero_faltante': 'Cero / faltantes',
}
rows = [[p('<b>Caso</b>', 'GCCell'), p('<b>Resultado observado</b>', 'GCCell'), p('<b>Web / móvil</b>', 'GCCell')]]
for case in matrix['casos']:
    rows.append([p(escape(labels[case['id']]), 'GCCell'), p(escape(case['observado']), 'GCCell'), p('Correcto / correcto', 'GCCell')])
table = Table(rows, colWidths=[119, 281, 115], hAlign='LEFT')
table.setStyle(TableStyle([
    ('BACKGROUND', (0, 0), (-1, 0), LIGHT), ('VALIGN', (0, 0), (-1, -1), 'TOP'),
    ('LEFTPADDING', (0, 0), (-1, -1), 8), ('RIGHTPADDING', (0, 0), (-1, -1), 8),
    ('TOPPADDING', (0, 0), (-1, -1), 7), ('BOTTOMPADDING', (0, 0), (-1, -1), 7),
    ('LINEBELOW', (0, 0), (-1, 0), 0.8, GREEN),
    ('INNERGRID', (0, 1), (-1, -1), 0.3, colors.HexColor('#D8E4DC')),
]))
story += [table, Spacer(1, 10), p('Las suites completas y el APK se enlazan en el registro técnico del repositorio. Laravel finalizó correctamente con 35 casos / 263 aserciones y avisos del entorno de pruebas. Esos avisos no se ocultan como una ejecución sin advertencias.', 'GCSmall'), PageBreak()]

story += [p('Consulta externa: web', 'GCH1'),
          p('Evidencia aportada por Jaziel. Warnes; 14/10/2026 00:00 a 21/10/2026 00:00 Bolivia, final excluido. Una detección, confianza nominal, FRP 3,43 MW y observación del 20/10/2026 a las 13:32 Bolivia.'),
          picture('01-web-consulta-mapa-lista.png', 515, 515), Spacer(1, 12),
          p('La captura muestra consulta, resumen del conjunto, mapa y listado. El mismo identificador se revisó en el detalle de ambas interfaces. Las capturas por sí solas no acreditan que las solicitudes compartieran la misma versionConjunto; el replay automatizado sí compara una fuente fija idéntica.', 'GCSmall'),
          p(link('Capturas originales versionadas y matriz completa', 'https://github.com/elingeyesdev/t6-medicion-ambiental/tree/develop/docs/qa21'), 'GCSmall'), PageBreak()]

story += [p('La misma consulta: Flutter', 'GCH1'),
          p('Capturas originales de Jaziel: mapa/resumen y detalle/fuente. Se conservan el registro, el período, la fecha observada, la confianza, la unidad y el producto externo.'),
          Table([[picture('03-movil-mapa-resumen.png', 242, 530), picture('05-movil-detalle-fuente.png', 242, 530)]], colWidths=[257, 258], style=[('VALIGN', (0, 0), (-1, -1), 'TOP')]),
          Spacer(1, 12), p('En la captura del mapa se informa la falta del límite territorial y se mantienen los datos textuales y el detalle. No se atribuye al revisor una nueva ejecución del emulador ni se presenta la degradación cartográfica como desaparición de los datos.', 'GCSmall'), PageBreak()]

story += [p('Demostración y revisión final', 'GCH1'),
          p('1. Iniciar el backend', 'GCH2'),
          p('En la raíz del repositorio oficial, con Laravel preparado y la clave NASA únicamente en web/.env:<br/><font name="Courier" size="9">.\\iniciar.ps1 -Direccion 0.0.0.0 -Puerto 8002</font>'),
          p('Abrir http://127.0.0.1:8002/modulos/focos-calor. La consulta histórica fotografiada puede dejar de estar disponible en el proveedor; para una nueva demostración elegir un período admitido por la API.'),
          p('2. Iniciar Flutter', 'GCH2'),
          p('Desde movil/, con Flutter 3.38.3 y emulador Android:<br/><font name="Courier" size="9">flutter pub get<br/>flutter run</font><br/>La base predeterminada es http://10.0.2.2:8002/api/v1/. En un teléfono físico configurar la dirección LAN del backend; nunca incluir la clave NASA en el cliente.'),
          p('3. Qué mostrar', 'GCH2'),
          p('Elegir iguales territorio, período y confianza. Revisar mapa/listado, abrir el mismo registro y contrastar fecha, FRP/unidad y fuente. Mostrar un vacío válido, explicar la cobertura y verificar reintento/expiración con los escenarios controlados documentados.'),
          p('4. Qué falta para cerrar', 'GCH2'),
          p('<b>Jaziel debe revisar el paquete ENT-02 / PR #27.</b> Después se registra la aceptación de #22 y de M2. La promoción a main requiere aprobación expresa del ingeniero. Integrar código en develop o crear este PDF no declara esa aprobación ni despliega producción.'),
          p('Referencias de la entrega', 'GCH2'),
          p(link('MOV-02 / PR #32', 'https://github.com/elingeyesdev/t6-medicion-ambiental/pull/32') + '<br/>' +
            link('QA-01 / PR #33', 'https://github.com/elingeyesdev/t6-medicion-ambiental/pull/33') + '<br/>' +
            link('ENT-02 / PR #27', 'https://github.com/elingeyesdev/t6-medicion-ambiental/pull/27') + '<br/>' +
            link('Contrato y arranque compartido', 'https://github.com/elingeyesdev/t6-medicion-ambiental/tree/develop/compartido/api'), 'GCBody'),
          p('La referencia completa de EnviroSense se conserva por separado en MIG-01 / #29, mediante PR #30 y #31. Su revisión no se confunde con el cierre de los cinco módulos ni con la aceptación académica de M2.', 'GCSmall')]

document = SimpleDocTemplate(str(OUTPUT), pagesize=A4, rightMargin=40, leftMargin=40,
                             topMargin=42, bottomMargin=58,
                             title='GeoCruz - Segunda iteración M2', author='Josué Padilla y Jaziel Díaz')
document.build(story, onFirstPage=footer, onLaterPages=footer)
print(OUTPUT)
