"""Orquestación con transporte y productor controlados; no publica en el mapa real."""
from datetime import datetime, timedelta, timezone
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from actualizar import actualizar, ciclo_disponible, escribir_json, Ocupado

CAPAS = ['temperatura', 'humedad', 'presion', 'visibilidad', 'nieve', 'lluvia_intensidad', 'lluvia', 'viento']


class ActualizarTest(unittest.TestCase):
    def setUp(self):
        self.temporal = tempfile.TemporaryDirectory()
        self.raiz = Path(self.temporal.name)
        self.ahora = datetime(2026, 10, 30, 2, tzinfo=timezone.utc)
        self.consultas, self.esperas, self.lotes = [], [], []

    def tearDown(self):
        self.temporal.cleanup()

    def adquirir(self, ciclo, plazo, conjunto):
        self.assertTrue(conjunto)
        self.consultas.append((ciclo, plazo))
        return b'fixture-acotado', {'estado': 'descargado', 'ciclo': ciclo, 'plazo_horas': plazo}

    def transformar(self, entrada, salida):
        # Solo simula la interfaz del productor real, probado aparte con GRIB controlados.
        plazo = int(entrada.stem.split('-')[-1])
        ciclo = datetime.strptime(entrada.parent.name[:10], '%Y%m%d%H').replace(tzinfo=timezone.utc)
        tiempo = {'ciclo': ciclo.strftime('%Y-%m-%dT%H:%M:%SZ'), 'fin_horas': plazo}
        salida.mkdir()
        cuadros = []
        for capa in CAPAS:
            archivo = f'{capa}-{plazo}.png'
            (salida/archivo).write_bytes(b'fixture, nunca un raster ambiental')
            cuadros.append({'archivo': archivo, 'capa': capa})
        escribir_json(salida/'receta.json', {'version': 1, 'cuadros': cuadros})
        return {'datos_controlados': False, 'campos': [{'tiempo': tiempo} for _ in CAPAS]}

    def publicar(self, lote):
        receta = json.loads((lote/'receta.json').read_text(encoding='utf-8'))
        self.assertEqual(len(receta['cuadros']), 16)
        self.assertEqual(len(list(lote.glob('*.png'))), 16)
        self.lotes.append(lote)
        return {'estado': 'publicado', 'nuevos': 16}

    def ejecutar(self, **extra):
        return actualizar(self.raiz, self.publicar, adquirir=self.adquirir, transformar=self.transformar,
                          esperar=self.esperas.append, **({'ahora': self.ahora} | extra))

    def test_ciclo_margen_y_cambio_de_dia_con_zonas(self):
        self.assertEqual(ciclo_disponible(self.ahora), '2026102918')
        self.assertEqual(ciclo_disponible(datetime(2026, 10, 30, 6, tzinfo=timezone.utc)), '2026103000')
        self.assertEqual(ciclo_disponible(self.ahora.astimezone(timezone(timedelta(hours=-4)))), '2026102918')
        with self.assertRaises(ValueError):
            ciclo_disponible(datetime(2026, 10, 30, 6))

    def test_lote_completo_espera_y_repeticion_no_descargan_otra_vez(self):
        self.assertEqual(self.ejecutar()['estado'], 'publicado')
        self.assertEqual(self.consultas, [('2026102918', 3), ('2026102918', 6)])
        self.assertEqual(self.esperas, [10])
        self.assertEqual(self.ejecutar()['estado'], 'al_dia')
        self.assertEqual(len(self.consultas), 2)
        self.assertEqual(len(self.lotes), 1)

    def test_fallo_de_una_descarga_conserva_ultimo_ciclo_y_espacia_reintentos(self):
        escribir_json(self.raiz/'estado.json', {'ultimo_ciclo_publicado': '2026102912'})
        llamadas = []
        def fallar(ciclo, plazo, conjunto):
            llamadas.append(plazo)
            return None, {'estado': 'fallido', 'http': 503}
        with self.assertRaises(ValueError):
            actualizar(self.raiz, self.publicar, self.ahora, fallar, self.transformar, self.esperas.append)
        self.assertEqual(llamadas, [3])
        self.assertEqual(self.lotes, [])
        estado = json.loads((self.raiz/'estado.json').read_text())
        self.assertEqual(estado['ultimo_ciclo_publicado'], '2026102912')
        self.assertEqual(self.ejecutar()['estado'], 'esperando')
        self.assertEqual(self.consultas, [])
        self.assertEqual(self.ejecutar(ahora=self.ahora+timedelta(hours=1))['estado'], 'publicado')

    def test_segundo_archivo_fallido_no_publica_el_primero(self):
        def adquirir(ciclo, plazo, conjunto):
            return self.adquirir(ciclo, plazo, conjunto) if plazo == 3 else (None, {'estado': 'fallido'})
        with self.assertRaises(ValueError):
            actualizar(self.raiz, self.publicar, self.ahora, adquirir, self.transformar, self.esperas.append)
        self.assertEqual(self.lotes, [])
        self.assertEqual(self.esperas, [10])

    def test_ciclo_incorrecto_y_publicacion_fallida_no_se_marcan_como_exito(self):
        def incorrecto(entrada, salida):
            metadata = self.transformar(entrada, salida)
            metadata['campos'][0]['tiempo']['ciclo'] = '2020-01-01T00:00:00Z'
            return metadata
        with self.assertRaises(ValueError):
            actualizar(self.raiz, self.publicar, self.ahora, self.adquirir, incorrecto, self.esperas.append)
        self.assertEqual(self.lotes, [])
        def fallo_publicar(lote):
            raise ValueError('Publicador rechazó el lote')
        with self.assertRaises(ValueError):
            actualizar(self.raiz, fallo_publicar, self.ahora+timedelta(hours=1), self.adquirir, self.transformar, self.esperas.append)
        estado = json.loads((self.raiz/'estado.json').read_text())
        self.assertNotIn('ultimo_ciclo_publicado', estado)
        self.assertEqual(estado['estado'], 'fallido')

    def test_otro_proceso_mantiene_el_bloqueo_sin_descargas_paralelas(self):
        modulo = str(Path(__file__).resolve().parents[1])
        programa = "import sys; from pathlib import Path; sys.path.insert(0,sys.argv[1]); from actualizar import bloqueo;\nwith bloqueo(Path(sys.argv[2])):\n print('listo',flush=True); sys.stdin.readline()"
        hijo = subprocess.Popen([sys.executable, '-c', programa, modulo, str(self.raiz/'actualizacion.lock')],
                                stdin=subprocess.PIPE, stdout=subprocess.PIPE, text=True)
        try:
            self.assertEqual(hijo.stdout.readline().strip(), 'listo')
            with self.assertRaises(Ocupado):
                self.ejecutar()
            self.assertEqual(self.consultas, [])
        finally:
            hijo.communicate('\n', timeout=5)
        self.assertTrue((self.raiz/'actualizacion.lock').exists())
        self.assertEqual(self.ejecutar()['estado'], 'publicado')


if __name__ == '__main__':
    unittest.main()
