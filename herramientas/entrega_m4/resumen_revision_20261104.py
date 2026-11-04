from pathlib import Path
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, PageBreak, Image
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib import colors
from reportlab.lib.units import cm
BASE=Path(__file__).resolve().parents[2]
OUT=BASE/'output/pdf/resumen-m4-20261104.pdf'
OUT.parent.mkdir(parents=True,exist_ok=True)
styles=getSampleStyleSheet()
styles.add(ParagraphStyle(name='TituloGeo',fontName='Helvetica-Bold',fontSize=26,leading=30,textColor=colors.HexColor('#155b3d'),spaceAfter=16))
styles['Heading2'].textColor=colors.HexColor('#155b3d')
styles['BodyText'].fontSize=11
styles['BodyText'].leading=16
styles['BodyText'].spaceAfter=9
story=[]
def p(t,style='BodyText'):story.append(Paragraph(t,styles[style]))
def link(n,pr=False):return '<a href="https://github.com/elingeyesdev/t6-medicion-ambiental/'+('pull/' if pr else 'issues/')+str(n)+'" color="#155b3d">#'+str(n)+'</a>'
p('GeoSur | Revisión de M4','TituloGeo')
p('Corte: 04/11/2026 - Preparación para revisar, sin aceptación final.')
p('Lo que ya se puede revisar','Heading2')
p('<b>Consulta de agua.</b> PR '+link(138,True)+': filtros de lugar y fechas, permisos, catálogo sin productos y contrato candidato. No publica ejemplos como resultados reales.')
p('<b>Vista web.</b> PR '+link(139,True)+': figuras de prueba dentro del mapa existente, selección y centrado, cierre del panel y estados sin cambios, sin datos y error. Solo revisión local; bloqueada en producción.')
p('<b>Fuente forestal.</b> PR '+link(119,True)+': cuatro consultas reales, serie de 25 años y contraste del territorio. Falta acordar criterio, límites y piloto. No acredita un módulo terminado.')
p('<b>Planner.</b> PR '+link(123,True)+': conserva la línea base. Capacidad confirmada: 2-3 horas por persona, de lunes a domingo. Faltan fecha de inicio, ausencias y esfuerzo restante para dar un pronóstico.')
p('El siguiente paso para un resultado real','Heading2')
p('Acordar el caso y método en '+link(110)+' / PR '+link(120,True)+', procesar y verificar '+link(111)+', completar consulta '+link(112)+' y web '+link(113)+', y contrastar ambos clientes en '+link(115)+'. Flutter '+link(114)+' y '+link(89)+' quedan con Jaziel. Railway '+link(49)+' sigue pospuesto.')
p('Pruebas y alcance','Heading2')
p('API: 23 pruebas Laravel y 8 del contrato. Vista web: 27 Laravel (incluyen las anteriores) y 188 de JavaScript. Recolector: 43 pruebas. Estos recuentos se solapan; no se suman como pruebas únicas.')
p('La captura reúne 22 tareas y 17 PR. El índice registra dependencias y revisiones, sin certificar entrega ni CI. El objetivo original de M4 es 05/11; no es un pronóstico recalculado ni una evaluación académica confirmada.')
story.append(PageBreak())
p('Guion de la revisión local','TituloGeo')
p('Abrir la rama de '+link(139,True)+', seguir docs/agua113/README.md y activar AGUA_REVISION_LOCAL=true solo en local. Entrar con una cuenta activa y abrir /revision/agua.')
p('1. Elegir Cambios de agua y seleccionar Agua nueva en Ubicaciones. El mapa debe centrar y resaltar la figura. Cerrar y reabrir el panel.')
p('2. Probar Sin cambios: cambios en cero. Probar Sin datos: métricas ausentes, nunca cero. Probar Error: se retiran las figuras anteriores. El aviso Datos de prueba siempre debe seguir visible.')
p('3. Mostrar el catálogo protegido sin productos y el 503 PRODUCTO_NO_DISPONIBLE. Separar este ensayo de las evidencias reales de proveedores.')
story.append(Image(str(BASE/'docs/entrega-m4/evidencias/2026-11-04/seleccion-controlada.png'),width=16*cm,height=9*cm))
p('Captura controlada de la interfaz. HTML renderizado por Laravel; servidor estático temporal, sin fondo cartográfico configurado. Las figuras y superficies son sintéticas. No representa una inundación real.')
p('Referencias de este corte','Heading2')
p('docs/entrega-m4/REVISION-20261104.md y evidencias/2026-11-04 contienen la guía, captura de tareas, índice y pruebas. Los estados posteriores se revisan en GitHub.')
def footer(canvas,doc):
 canvas.setFont('Helvetica',9);canvas.setFillColor(colors.HexColor('#52645c'));canvas.drawString(2*cm,1.2*cm,'GeoSur - Preparación de M4 - 04/11/2026');canvas.drawRightString(19*cm,1.2*cm,str(doc.page))
SimpleDocTemplate(str(OUT),pagesize=(21*cm,29.7*cm),rightMargin=2*cm,leftMargin=2*cm,topMargin=1.6*cm,bottomMargin=1.8*cm,title='GeoSur - Revisión de M4',author='Josue Padilla').build(story,onFirstPage=footer,onLaterPages=footer)
print(OUT)
