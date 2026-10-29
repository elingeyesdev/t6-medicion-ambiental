"""Genera el paquete M3 desde evidencias versionadas, sin leer cuentas ni secretos."""
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
    estilos.add(ParagraphStyle(name='TemaM3', fontName=fuente, fontSize=22, leading=28, textColor=VERDE, spaceAfter=17))
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
        escala = min(510 / w, max_alto / h)
        historia.append(Image(str(archivo), width=w * escala, height=h * escala, hAlign='LEFT'))
        historia.append(Spacer(1, 8))
        p(titulo, 'NotaM3')

    p('GeoSur', 'TituloM3')
    p('M3 - Recuperación web de EnviroSense', 'TemaM3')
    p('Candidato local para revisión · 29 de octubre de 2026')
    p('<b>Equipo:</b> Josué Padilla y Jaziel Díaz. Josué desarrolla la web y revisa el móvil; Jaziel desarrolla el móvil y revisa la web.')
    p('<b>Decisión de alcance:</b> EnviroSense es el software principal. GeoSur adapta sus funciones a Laravel/Blade y API compartida; los análisis de focos y sequías se incorporan dentro de ese sistema. El chatbot quedó retirado por decisión de Josué.')
    p('<b>Territorio:</b> el catálogo principal conserva doce países de Sudamérica y Guyana Francesa como territorio. Eso no acredita datos de todas las capas en todo el continente. Focos y sequías mantienen su cobertura probada de Santa Cruz, Bolivia.')
    p('<b>Entrega y aceptación:</b> este paquete reúne código y evidencia para revisar. No declara M3 aprobado, desplegado ni completamente conectado a proveedores. Main conserva M2, versión v0.2.0; la promoción de M3 requiere su propia aprobación académica.')
    p('Tecnología: Laravel, Blade, JavaScript y API REST. Plantilla web real: Tabler 1.7.1. Flutter queda a cargo de Jaziel. Se trabaja en local; Railway fue pospuesto.')
    p('El índice de archivos y los pasos completos están en docs/entrega-m3/README.md. Este documento se genera con herramientas/entregas/generar-m3.py.', 'NotaM3')

    pagina('Funciones recuperadas en la web')
    for titulo, texto in [
        ('Inicio y cuenta', 'Inicio, registro, ingreso, salida, recuperación, perfil, ubicación, unidades, contraseña, roles y estado de cuenta. La demo conserva su rol Visualizador. No se importaron cuentas privadas del original.'),
        ('Mapa y consultas', 'Países, 241 regiones, puntos, filtros, historial por momento, comparación, unidades, búsqueda de lugares y pronóstico. Se añadieron capas raster, reproducción y división A/B. La simulación continua tiene una capa explícita y separada.'),
        ('Mediciones e ingestión', 'Puntos, lecturas y gráficos; CSV e historial JSON revisado, validación por lote y deduplicación. Worker local y receptor MQTT configurables. Un punto del catálogo no se presenta como un sensor físico conectado.'),
        ('Simulaciones y reportes', 'Escenarios, controles, modo global compartido, ajustes manuales y cortes inmutables. Reportes propios, gráficos, PDF, Excel y plantillas. Los datos simulados y los estimados mantienen su identificación.'),
        ('Alertas y avisos', 'Umbrales originales, reconocimiento, historial y bandeja propia. Cola persistente para correo, Telegram, WhatsApp y push, con consentimiento y estados. Esos proveedores siguen desactivados y sin envío real probado.'),
        ('Análisis nuevos', 'Focos de calor integrado desde M2; sequías web/API y cliente de Jaziel con un caso contrastado. Inundaciones conserva un avance de adquisición y método; no hay resultado de inundación confirmado.'),
    ]:
        p('<b>'+titulo+'.</b> '+texto)

    pagina('La misma consulta de sequías')
    q = json.loads((RAIZ/'docs/qa39/resultados/contraste-josue-2026-10-29.json').read_text(encoding='utf-8'))
    p('Caso scz-piloto-01, mes final septiembre de 2024. El trimestre corresponde a julio-septiembre y el cálculo usa meses UTC. La referencia reúne treinta ventanas de 1991 a 2020.')
    p('<b>Lluvia:</b> 114,5 mm. <b>SPI de Laravel:</b> -0,6413769540120462. <b>Condición:</b> normal. El cliente de Jaziel muestra el SPI redondeado a -0,64.')
    p('<b>Contraste independiente:</b> SciPy '+escape(q['scipy'])+' devuelve '+str(q['referencia'])+'. Diferencia absoluta '+format(q['diferenciaAbsoluta'], '.3g')+', inferior a 1e-8. Se conservaron las treinta entradas y sus versiones; el oráculo antiguo de MET-01 no se sustituyó por esta captura.')
    foto('docs/qa39/evidencias/web-sequias-cobertura-2024-09.png', 'Sesión real de Josué: un municipio con datos de 56 y 55 pendientes, sin convertir los faltantes en cero.', 340)
    p('La ficha distingue Santa Cruz de La Sierra, municipio solicitado, de Porongo, donde cae el centro de la celda. El resultado es puntual; no acredita sequía de todo el municipio.', 'NotaM3')

    pagina('Capas reales en el mapa')
    p('Se recuperó el productor de temperatura y se probaron dos recortes pequeños de GFS/NOAA: ciclo 29/10/2026 06 UTC, plazos 000 y 003. Cada entrada ocupa 88 308 bytes. Las fechas válidas son 06 y 09 UTC, equivalentes a 02 y 05 en Bolivia.')
    p('Cada cuadro contiene 193 × 281 nodos. El PNG conserva geografía, máscara de ausencia, escala y huella. El visor proyecta la rejilla y permite comparar dos fechas. Se muestran datos modelados, sin presentarlos como lecturas de estaciones.')
    foto('docs/entrega-m3/evidencias/mapa-temperatura-noaa.png', 'Recorrido local real: cuadros de temperatura de NOAA, comparación A/B y fecha de cada lado.', 340)
    p('El productor local ERA5 se probó con un NetCDF controlado; no se descargó ERA5 ni se creó una cuenta CDS. Los otros productos raster todavía requieren productores e insumos verificados. La captura de temperatura no acredita todas las variables ni un histórico completo.', 'NotaM3')

    pagina('Cómo repetir la presentación')
    for n, texto in enumerate([
        'Arrancar desde el checkout activo con su entorno local; abrir 127.0.0.1:5791. Conservar la base y aplicar únicamente las migraciones normales, después de respaldarla. No ejecutar migrate:fresh sobre la base del usuario.',
        'Ingresar con la cuenta demo ya entregada. Abrir Mapa y recorrer país, región, variable y punto. Diferenciar registros, estimaciones y simulados. Consultar historial y comparar sin mezclar fuentes.',
        'En Capas del ambiente, activar Temperatura. Elegir las dos fechas publicadas, comparar A/B, pausar la reproducción y leer el centro. Los cuadros de esta evidencia son capturas fijas; no se actualizan al abrir el mapa.',
        'Abrir Sequías y el ejemplo de septiembre de 2024. Mostrar la cobertura parcial, abrir la ficha y explicar por qué una celda no representa toda una superficie municipal. Si caduca la publicación, renovarla explícitamente; no alterar su fecha.',
        'Con una cuenta autorizada, probar Simulaciones, un corte en Reportes y su PDF/Excel. El Visualizador no se eleva para demostrar funciones reservadas. Las pruebas de permisos usan cuentas controladas en bases aisladas.',
        'Abrir Mis avisos y Mi cuenta. No activar ni demostrar un envío externo sin credenciales, consentimiento y destinatario autorizado. La recepción de un proveedor y la lectura por una persona son estados diferentes.',
    ], 1):
        p('<b>'+str(n)+'.</b> '+texto)
    p('El código del referente se conserva inmutable en referencias/envirosense/codigo. No se ejecutó su SQL ni se copiaron sus bases, cuentas, contactos o claves.', 'NotaM3')

    pagina('Pruebas, GitHub y pendientes reales')
    p('<b>Integración comprobada:</b> 477 pruebas PHP en SQLite, 5717 comprobaciones; 100 pruebas JavaScript. PostgreSQL 16.11 pasó 476 pruebas y 5707 comprobaciones, incluidas importaciones simultáneas y conflictos. El clúster aislado quedó detenido. Once pruebas del productor raster y cinco del normalizador; Pint correcto.')
    p('<b>Revisión cruzada:</b> PR #87 y #82 integrados después de revisión. Tareas #38, #48, #60, #81 y #85 cerradas por el trabajo aceptado. La recuperación móvil ampliada sigue con Jaziel en #89.')
    p('<b>PR #88:</b> la comparación de sequías está comprobada en web, API y evidencia móvil, pero Android falló por un conflicto entre manifiestos. Se dejó a Jaziel el ajuste concreto. No se aprueba ni se cierra #39 con ese check fallido.')
    p('<b>Candidatos web:</b> #90 flujo del curso, #91 cargas e historial, #92 simulación y reportes, #93 capas, #94 avisos/cuenta, #95 productor de temperatura y #96 paquete M3. Requieren la revisión de Jaziel; el autor no aprueba su propio PR. Se conserva el comando para fusionar y borrar la rama temporal.')
    p('<b>Datos y servicios:</b> faltan las conexiones reales de sensores/MQTT y canales externos, histórico saneado y productores de las demás variables raster. La búsqueda de establecimientos depende del servicio configurado y no tiene cobertura probada en Bolivia. No se anuncia como conectada.')
    p('<b>Inundaciones:</b> #46 contiene propuesta, catálogo reproducible y once pruebas. Falta revisar objetivo/evento y pareja de escenas antes de procesar píxeles. No se publicó un mapa de agua como inundación confirmada.')
    p('<b>Entrega:</b> permanecen pendientes APK release de Jaziel, ajustes administrativos del propietario, revisión del cronograma ampliado, publicación pospuesta y aceptación académica M3. El candidato no sustituye esos pasos.')

    salida.parent.mkdir(parents=True, exist_ok=True)
    def pie(c, doc):
        c.setStrokeColor(colors.HexColor('#d6e4dc')); c.line(42, 35, A4[0]-42, 35)
        c.setFont(fuente, 9); c.setFillColor(GRIS)
        c.drawString(42, 22, 'GeoSur · Candidato M3 · 29/10/2026')
        c.drawRightString(A4[0]-42, 22, str(doc.page))
    SimpleDocTemplate(str(salida), pagesize=A4, rightMargin=42, leftMargin=42, topMargin=44, bottomMargin=48,
                      title='GeoSur - Candidato M3', author='Josué Padilla y Jaziel Díaz').build(historia, onFirstPage=pie, onLaterPages=pie)


if __name__ == '__main__':
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--salida', type=Path, default=RAIZ/'output/pdf/GeoSur-M3.pdf')
    generar(parser.parse_args().salida)
