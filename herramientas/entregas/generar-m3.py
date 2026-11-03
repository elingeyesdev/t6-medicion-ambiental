"""Genera el candidato M3 al 30/10/2026 con evidencias versionadas, sin secretos."""
from pathlib import Path
import json
import argparse
from xml.sax.saxutils import escape

from reportlab.lib import colors
from reportlab.lib.enums import TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.utils import ImageReader
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, PageBreak, Image, KeepTogether

RAIZ = Path(__file__).resolve().parents[2]
VERDE = colors.HexColor('#175c3b')
GRIS = colors.HexColor('#44505b')
ANCHO_CONTENIDO = A4[0] - 84 - 12  # Márgenes y relleno del marco de Platypus.


def generar(salida):
    regular = Path('C:/Windows/Fonts/segoeui.ttf')
    negrita = Path('C:/Windows/Fonts/segoeuib.ttf')
    fuente = 'Helvetica'
    if regular.is_file() and negrita.is_file():
        pdfmetrics.registerFont(TTFont('GeoSur', str(regular)))
        pdfmetrics.registerFont(TTFont('GeoSurBold', str(negrita)))
        pdfmetrics.registerFontFamily('GeoSur', normal='GeoSur', bold='GeoSurBold')
        fuente = 'GeoSur'
    estilos = getSampleStyleSheet()
    estilos.add(ParagraphStyle(name='TituloM3', fontName=fuente, fontSize=32, leading=38, textColor=VERDE, spaceAfter=20))
    estilos.add(ParagraphStyle(name='TemaM3', fontName=fuente, fontSize=22, leading=28, textColor=VERDE, spaceAfter=17, keepWithNext=True))
    estilos.add(ParagraphStyle(name='TextoM3', fontName=fuente, fontSize=11.5, leading=17, textColor=GRIS, spaceAfter=11, alignment=TA_LEFT))
    estilos.add(ParagraphStyle(name='NotaM3', fontName=fuente, fontSize=10, leading=14, textColor=GRIS, spaceAfter=10))
    historia = []
    def p(texto, estilo='TextoM3'):
        historia.append(Paragraph(texto, estilos[estilo]))
    def pagina(titulo):
        if historia:
            historia.append(PageBreak())
        p(titulo, 'TemaM3')
    def foto(ruta, titulo, max_alto=380):
        archivo = RAIZ / ruta
        if not archivo.is_file():
            raise FileNotFoundError('Falta la evidencia: '+str(archivo))
        w, h = ImageReader(str(archivo)).getSize()
        escala = min(ANCHO_CONTENIDO / w, max_alto / h)
        historia.append(KeepTogether([
            Image(str(archivo), width=w * escala, height=h * escala, hAlign='LEFT'),
            Spacer(1, 8),
            Paragraph(titulo, estilos['NotaM3']),
        ]))

    p('GeoSur', 'TituloM3')
    p('M3 - Recuperación web de EnviroSense', 'TemaM3')
    p('Candidato local para revisión · 30 de octubre de 2026')
    p('<b>Equipo:</b> Josué Padilla y Jaziel Díaz. Josué desarrolla la web y revisa el móvil; Jaziel desarrolla el móvil y revisa la web.')
    p('<b>Decisión de alcance:</b> EnviroSense es el software principal. GeoSur adapta sus funciones a Laravel/Blade y API compartida; los análisis de focos y sequías se incorporan dentro de ese sistema. El chatbot quedó retirado por decisión de Josué.')
    p('<b>Territorio:</b> el catálogo principal conserva doce países de Sudamérica y Guyana Francesa como territorio. Eso no acredita datos de todas las capas en todo el continente. Focos y sequías mantienen su cobertura probada de Santa Cruz, Bolivia.')
    p('<b>Entrega y aceptación:</b> este paquete reúne código y evidencia para revisar. M3 sigue siendo candidato, sin aprobación ni despliegue. Se conserva el corte histórico de M2 en main: v0.2.0, commit 851a79f5c699249c284001114f76f994c9ce4335. La promoción de M3 requiere revisión cruzada y aprobación académica propias.')
    p('<b>Avances versionados:</b> frente original recuperado (#98), actualización local GFS (#100), aire puntual EAQI (#102), reportes reales PDF/XLSX con dos escalas de aire (#104), partículas de viento (#106) y energía de superficie (#107). Son avances del candidato; los datos y servicios originales siguen incompletos.')
    p('Tecnología: Laravel, Blade, JavaScript y API REST. Se recuperó la presentación de EnviroSense sobre componentes internos de Tabler 1.7.1. Flutter queda a cargo de Jaziel. Railway continúa pospuesto.')
    p('El índice de archivos y los pasos completos están en docs/entrega-m3/README.md. Este documento se genera con herramientas/entregas/generar-m3.py.', 'NotaM3')

    pagina('Frente y funciones recuperadas')
    p('<b>PR #98:</b> portada, acceso, navegación, mapa y pantallas internas recuperan la composición de EnviroSense en Blade. Tabler 1.7.1 mantiene los componentes internos; la adaptación visual sigue sujeta a revisión.')
    foto('docs/evidencias/frente47/geosur-inicio.png', 'Portada recuperada con la marca GeoSur. Referencia y recorrido: docs/evidencias/frente47/README.md.', 280)
    for titulo, texto in [
        ('Inicio y cuenta', 'Registro, acceso, recuperación, perfil, ubicación, unidades, contraseña y roles. La demo sigue como Visualizador; las cuentas privadas del original no se importaron.'),
        ('Mapa y consultas', 'Países, 241 regiones, puntos, filtros, historial, comparación A/B, búsqueda y pronóstico. Capas raster con fechas propias; simulación continua identificada por separado.'),
        ('Mediciones e ingestión', 'Lecturas, gráficos, CSV/JSON, validación y deduplicación; worker y receptor MQTT configurables. Los puntos de referencia no acreditan sensores físicos conectados.'),
        ('Simulaciones y reportes', 'Escenarios, controles por rol, modo compartido, cortes inmutables, gráficos, PDF, Excel y plantillas. Se identifica el origen simulado o estimado.'),
        ('Alertas y avisos', 'Umbrales, reconocimiento y bandeja propia. Cola con consentimiento para correo, Telegram, WhatsApp y push; canales apagados, sin envío real probado.'),
        ('Análisis GeoSur', 'Focos desde M2 y sequías web/API con un caso contrastado con Jaziel. Inundaciones conserva adquisición y método parciales, sin resultado confirmado.'),
    ]:
        p('<b>'+titulo+'.</b> '+texto)

    pagina('La misma consulta de sequías')
    q = json.loads((RAIZ/'docs/qa39/resultados/contraste-josue-2026-10-29.json').read_text(encoding='utf-8'))
    p('Caso scz-piloto-01, mes final septiembre de 2024. El trimestre corresponde a julio-septiembre y el cálculo usa meses UTC. La referencia reúne treinta ventanas de 1991 a 2020.')
    p('<b>Lluvia:</b> 114,5 mm. <b>SPI de Laravel:</b> -0,6413769540120462. <b>Condición:</b> normal. El cliente de Jaziel muestra el SPI redondeado a -0,64.')
    p('<b>Contraste independiente:</b> SciPy '+escape(q['scipy'])+' devuelve '+str(q['referencia'])+'. Diferencia absoluta '+format(q['diferenciaAbsoluta'], '.3g')+', inferior a 1e-8. Se conservaron las treinta entradas y sus versiones; el oráculo antiguo de MET-01 no se sustituyó por esta captura.')
    foto('docs/qa39/evidencias/web-sequias-cobertura-2024-09.png', 'Sesión real de Josué: un municipio con datos de 56 y 55 pendientes, sin convertir los faltantes en cero.', 340)
    p('La ficha distingue Santa Cruz de La Sierra, municipio solicitado, de Porongo, donde cae el centro de la celda. El resultado es puntual; no acredita sequía de todo el municipio.', 'NotaM3')

    pagina('Capas GFS y viento animado')
    p('<b>Base real:</b> ocho capas GFS/NOAA: temperatura, humedad, viento, presión, visibilidad, nieve equivalente de agua y precipitación acumulada/instantánea. El primer corte conserva 17 PNG y rejillas de 193 × 281 nodos. Son estimaciones modeladas; el acumulado incluye fase líquida y sólida y exige un intervalo explícito.')
    p('<b>Actualización local (#100):</b> el ciclo 29/10/2026 18 UTC, plazos 003/006, añadió 16 imágenes sin borrar las 17 anteriores: 33 en esa captura. En el corte actual de #107, el horario también incorporó el ciclo siguiente y terminó con resultado 0. Revisa cada hora y necesita equipo y sesión disponibles. La web avisa de nuevas fechas y conserva la selección A/B.')
    p('<b>Partículas (#106):</b> Animar viento usa los vectores de la misma imagen y fecha de cada lado A/B. Empieza desactivado, permite pausa y se limpia al mover o retirar la capa. La calma y los huecos no dibujan trazos; no crea lecturas ni adelanta el tiempo del pronóstico.')
    foto('docs/evidencias/viento105/comparacion-viento.png', 'Captura del 30/10: viento A/B sobre PNG GFS reales, del 29/10 a las 20:00 y 05:00 de Bolivia. Una imagen fija no demuestra por sí sola la animación.', 330)
    p('El desplazamiento de los trazos es ilustrativo: no calcula advección física, humo o propagación de incendios. Guías: docs/ACTUALIZACION_CAPAS.md y docs/VIENTO_ENVIROSENSE.md. ERA5 solo tiene ensayo con NetCDF controlado; no se descargó ese producto.', 'NotaM3')

    pagina('Radiación y evaporación potencial')
    p('<b>PR de la tarea #107:</b> se recuperaron las dos variables del operativo original con sus magnitudes correctas. Radiación solar es un promedio de intervalo en W/m²; evaporación potencial es energía instantánea en W/m². No son índice UV ni milímetros de agua evaporada.')
    p('<b>Captura comprobada:</b> ciclo 29/10/2026 18 UTC, plazos 003/006. Dos peticiones públicas pequeñas y cuatro PNG. Se contrastaron los píxeles con los GRIB: error máximo de cuantización inferior a 3,14 W/m² en radiación y a 3,93 W/m² en PEVPR. Se conservaron máscaras, signo y cero válido; no se rellenan océanos o huecos.')
    foto('docs/evidencias/energia107/mapa-energia.png', 'A: radiación solar media de 14:00 a 17:00 de Bolivia. B: PEVPR instantáneo de las 17:00. Sus escalas son distintas; ambos provienen de GFS, no de estaciones.', 350)
    p('El perfil de actualización es independiente y conserva las ocho capas anteriores. La nueva captura del 30/10 00 UTC también se publicó; repetir el comando devolvió al_dia sin descarga. Guía y recibos: docs/ENERGIA_ENVIROSENSE.md.', 'NotaM3')

    pagina('Calidad del aire del lugar')
    p('<b>PR #102:</b> la ficha del mapa recupera el índice europeo de EnviroSense, identificado como EAQI, con CAMS global/Open-Meteo. Incluye partículas PM2,5/PM10, otros contaminantes, fecha, fuente y últimas 24 horas. La consulta se inicia al pulsar Calidad del aire; conserva la caché de quince minutos y distingue cero de faltante.')
    p('<b>Captura real fechada:</b> Santa Cruz, dato válido del 30/10/2026 03:00 UTC, equivalente al 29/10 a las 23:00 en Bolivia. Índice europeo 47; PM2,5 de 17,3 μg/m³ y PM10 de 18 μg/m³. Son estimaciones de una celda, no mediciones de una estación ni una condición permanente de la ciudad.')
    foto('docs/evidencias/aire101/santa-cruz-aire.png', 'Ficha real de Santa Cruz: escala europea, fecha y partículas. Fuente y recibos: docs/evidencias/aire101/README.md.', 340)
    p('La consulta actual es independiente del período de lecturas guardadas. Sus 24 horas no completan el histórico original ni la capa raster continental AQI. EAQI y USAQI conservan sus escalas; no se convierten entre sí ni se atribuye una escala a las lecturas antiguas aqi/AQI.', 'NotaM3')
    p('Guía y límites: docs/CALIDAD_AIRE_ENVIROSENSE.md. CAMS global tiene resolución declarada de 0,4° (unos 45 km); la frecuencia horaria no lo convierte en una red de estaciones.', 'NotaM3')

    pagina('Reportes reales y exportaciones')
    p('<b>PR #104:</b> el reporte atmosférico distingue Aire · escala de Estados Unidos (USAQI) y Aire · escala europea (EAQI) en selector, filas, resúmenes, gráficos y exportaciones. Las lecturas y los cortes antiguos conservan su contenido; cada escala mantiene su fuente.')
    p('<b>Comprobación del 30/10/2026, 00:43 de Bolivia:</b> dos respuestas públicas reales para Santa Cruz y las 00:00, 01:00 y 02:00 del mismo día. Nueve valores, sin faltantes, con lugar, celda, zona UTC, horas y unidades verificados. Es un pronóstico modelado; incluye horas futuras identificadas por su fecha y tipo de reporte.')
    p('<b>Valores contrastados:</b> temperatura 20,9 / 20,5 / 20,3 °C; aire estadounidense 53 / 54 / 54 USAQI; aire europeo 44 / 41 / 34 EAQI. No se comparan ambas escalas como un único índice.')
    foto('docs/evidencias/reportes103/excel-lecturas.png', 'Hoja Lecturas del XLSX real: fecha, lugar, variable, valor, unidad y procedencia. Las dos escalas permanecen separadas.', 110)
    p('<b>PDF/XLSX comprobados:</b> PDF de cuatro páginas y Excel con Lecturas/Resumen. Un lector Python independiente contrastó cada valor y unidad con las respuestas y el Excel, los tres resúmenes, huellas y numeración del PDF. Se revisaron las cuatro páginas y las dos hojas. Exportar y volver a presentar el corte guardado no consultó proveedores.')
    p('<b>Entorno y permisos:</b> se usaron una cuenta temporal y SQLite en memoria; la demo quedó intacta. En este equipo se habilitó solo la opción de Reportes atmosféricos; una instalación nueva la conserva apagada. Analista/Administrador generan y Visualizador consulta sus reportes propios.')
    p('Guía: docs/REPORTES_ATMOSFERICOS_VERIFICADOS.md. Respuestas, recibo, auditoría y vistas: docs/evidencias/reportes103/README.md. Esta captura no completa sensores ni datos privados originales.', 'NotaM3')

    pagina('Cómo repetir la presentación')
    for n, texto in enumerate([
        'Arrancar desde el checkout activo con su entorno local; abrir 127.0.0.1:5791. Conservar la base y aplicar únicamente las migraciones normales, después de respaldarla. No ejecutar migrate:fresh sobre la base del usuario.',
        'Recorrer portada y acceso recuperados. Ingresar con la demo ya entregada, que conserva Visualizador. En Mapa, recorrer país, región, variable y punto; distinguir registros, estimaciones y simulados.',
        'En Capas del ambiente, mostrar una de las diez variables comprobadas. Elegir fechas A/B y leer el centro; revisar los períodos de precipitación y radiación solar. Para viento, activar/desactivar Animar viento y mover la división. El movimiento no cambia la fecha. Las nuevas fechas se aplican con su control; abrir el mapa no descarga GFS.',
        'Seleccionar Santa Cruz y pulsar Calidad del aire; mostrar EAQI, fecha, partículas y últimas 24 horas. La consulta es actual e independiente del historial del mapa. Una nueva consulta puede devolver valores distintos de la captura.',
        'Abrir Sequías y el ejemplo de septiembre de 2024. Mostrar la cobertura parcial y explicar por qué una celda no representa toda la superficie municipal. Si caduca la publicación, renovarla explícitamente; no alterar su fecha.',
        'Con una cuenta autorizada, recorrer Simulaciones y Área de Reportes. En Pronóstico Atmosférico, separar USAQI/EAQI y exportar PDF/Excel del corte. El servicio debe estar habilitado; no elevar a la demo para demostrar generación. La comprobación real documentada usa una base temporal.',
        'Abrir Mis avisos y Mi cuenta. No activar ni demostrar un envío externo sin credenciales, consentimiento y destinatario autorizado. La recepción de un proveedor y la lectura por una persona son estados diferentes.',
    ], 1):
        p('<b>'+str(n)+'.</b> '+texto)
    p('El código del referente se conserva inmutable en referencias/envirosense/codigo. No se ejecutó su SQL ni se copiaron sus bases, cuentas, contactos o claves.', 'NotaM3')

    pagina('Pruebas, GitHub y pendientes reales')
    p('<b>Corte base de #96 (29/10):</b> 477 pruebas PHP/5717 comprobaciones en SQLite y 101 JavaScript. PostgreSQL 16.11: 476 pruebas/5707 comprobaciones, con concurrencia; clúster aislado detenido. Productor raster: 22 pruebas; normalizador: cinco. Se conserva este registro, sin presentarlo como una ejecución actual de toda la cadena.')
    p('<b>Validaciones posteriores registradas:</b> #100: 486 PHP y 28 Python; #102: 496 PHP/6009 comprobaciones y 110 JavaScript; #104: 501 PHP/6057 antes del ajuste de paginación y 29 de Reportes/238 después; #106: 119 JavaScript y 28 PHP de mapa/capas/212 comprobaciones. Pint correcto en esas entregas. Son alcances distintos, no un total sumable.')
    p('<b>Revisión cruzada:</b> PR #87 y #82 integrados después de revisión. Tareas #38, #48, #60, #81 y #85 cerradas por el trabajo aceptado. La recuperación móvil ampliada sigue con Jaziel en #89.')
    p('<b>PR #88:</b> mismo head 1604a1a; Android sigue bloqueado por el conflicto entre manifiestos. Web, API y evidencia móvil conservan el contraste de sequías, pero #39 no se cierra con ese check fallido. El ajuste corresponde a Jaziel.')
    p('<b>Secuencia de revisión:</b> #90-95 y #97 sostienen el paquete #96, aún pendiente de Jaziel. Continúa con #98 frente, #100 horario GFS, #102 aire puntual, #104 reportes, #106 viento y la tarea #107. Cada propuesta conserva su revisión propia; su implementación no acredita aceptación M3.')
    pagina('Estado de entrega y pendientes')
    p('<b>Energía recuperada (#107):</b> DSWRF media de intervalo y PEVPR instantáneo en W/m², con cuatro PNG reales del ciclo 29/10 18 UTC y otros cuatro del 30/10 00 UTC. El operativo original los etiquetaba UV/evaporación; no son índice UV ni mm. Se conservaron las 33 imágenes iniciales y el horario también añadió su nuevo ciclo ambiental: 57 cuadros en el corte final. Auditoría de píxeles y captura en docs/evidencias/energia107/.')
    p('<b>Validación actual (#107):</b> 503 pruebas PHP/6069 comprobaciones, 121 JavaScript y 34 del productor/orquestación; Pint correcto. Se probó el perfil de energía real y su repetición sin otra descarga. La tarea Windows existente terminó con resultado 0 el 30/10 a las 02:33 de Bolivia.')
    p('<b>Datos pendientes:</b> sensores/broker reales, histórico original saneado, AQI raster continental, índice UV raster y evaporación histórica ERA5. La recuperación del operativo no completa esos otros insumos ni las conexiones originales.')
    p('<b>Servicios:</b> correo, Telegram, WhatsApp y push siguen apagados y sin envío real probado. Reportes sí tiene la consulta externa fechada descrita. La búsqueda de establecimientos depende del servicio configurado, sin cobertura probada en Bolivia. Los servicios originales no están completos.')
    p('<b>Inundaciones:</b> #46 contiene propuesta, catálogo reproducible y once pruebas. Falta revisar objetivo/evento y pareja de escenas antes de procesar píxeles. No se publicó un mapa de agua como inundación confirmada.')
    p('<b>Entrega:</b> permanecen pendientes APK release de Jaziel, ajustes administrativos del propietario, revisión del cronograma ampliado, publicación pospuesta y aceptación académica M3. El candidato no sustituye esos pasos.')

    salida.parent.mkdir(parents=True, exist_ok=True)
    def pie(c, doc):
        c.setStrokeColor(colors.HexColor('#d6e4dc')); c.line(42, 35, A4[0]-42, 35)
        c.setFont(fuente, 9); c.setFillColor(GRIS)
        c.drawString(42, 22, 'GeoSur · Candidato M3 · 30/10/2026')
        c.drawRightString(A4[0]-42, 22, str(doc.page))
    SimpleDocTemplate(str(salida), pagesize=A4, rightMargin=42, leftMargin=42, topMargin=44, bottomMargin=48,
                      title='GeoSur - Candidato M3', author='Josué Padilla y Jaziel Díaz').build(historia, onFirstPage=pie, onLaterPages=pie)


if __name__ == '__main__':
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--salida', type=Path, default=RAIZ/'output/pdf/GeoSur-M3.pdf')
    generar(parser.parse_args().salida)
