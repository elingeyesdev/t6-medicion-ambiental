from contextlib import redirect_stderr, redirect_stdout
import hashlib
import io
import json
from pathlib import Path
import shutil
import struct
import tempfile
import unittest
from unittest.mock import patch

from PIL import Image
from pyproj import Geod
from recorte_raster import (Rangos, cabecera, calcular, geometria, limites,
                           main, mini_tiff, leer_banda, BANDAS, MAX_BYTES)

ROOT = Path(__file__).resolve().parents[2]
EVIDENCIA = ROOT / "docs/forestal147/evidencias/recorte-20261108"
CAPTURA = EVIDENCIA / "captura"


class Respuesta:
    def __init__(self, dato=b"abcd", estado=206, **cabeceras):
        self.status = estado
        self.headers = {"Content-Range": "bytes 0-3/100",
                        "ETag": '"' + "a"*32 + '"', **cabeceras}
        self.dato, self.leido = dato, False

    def __enter__(self):
        return self

    def __exit__(self, *_):
        pass

    def read(self, cantidad):
        self.leido = True
        return self.dato[:cantidad]


class RangoTest(unittest.TestCase):
    def abrir_fuente(self, respuesta, carpeta):
        return Rangos(Path(carpeta) / "captura", abrir=lambda *a, **k: respuesta)

    def test_rechaza_http_200_sin_leer_cuerpo(self):
        r = Respuesta(estado=200)
        with tempfile.TemporaryDirectory() as t:
            with self.assertRaises(ValueError):
                self.abrir_fuente(r, t).leer("lossyear", 0, 4)
        self.assertFalse(r.leido)

    def test_rechaza_respuestas_incompatibles(self):
        casos = [
            Respuesta(dato=b"abc"),
            Respuesta(dato=b"abcde"),
            Respuesta(**{"Content-Range": "bytes 1-4/100"}),
            Respuesta(**{"Content-Range": "bytes 0-3/*"}),
            Respuesta(**{"Content-Range": "bytes 0-3/3"}),
            Respuesta(**{"ETag": 'W/"' + "a"*32 + '"'}),
            Respuesta(**{"Content-Encoding": "gzip"}),
        ]
        for r in casos:
            with self.subTest(cabeceras=r.headers, largo=len(r.dato)), \
                    tempfile.TemporaryDirectory() as t, self.assertRaises(ValueError):
                self.abrir_fuente(r, t).leer("lossyear", 0, 4)

    def test_rechaza_rangos_grandes_y_desconocidos_antes_de_red(self):
        with tempfile.TemporaryDirectory() as t:
            f = Rangos(Path(t) / "cap", abrir=lambda *a, **k: self.fail("red"))
            for banda, inicio, cantidad in [("otra", 0, 4), ("lossyear", -1, 4),
                                           ("lossyear", 0, 3_000_001),
                                           ("lossyear", True, 4), ("lossyear", 0, 0)]:
                with self.subTest(banda=banda, inicio=inicio, cantidad=cantidad), \
                        self.assertRaises(ValueError):
                    f.leer(banda, inicio, cantidad)
            f.bytes = MAX_BYTES
            with self.assertRaises(ValueError):
                f.leer("lossyear", 0, 1)

    def test_version_fija_y_peticion_condicional(self):
        pedidos = []
        def abrir(request, timeout):
            pedidos.append(request)
            return Respuesta()
        with tempfile.TemporaryDirectory() as t:
            f = Rangos(Path(t) / "cap", abrir=abrir)
            f.leer("lossyear", 0, 4)
            f.leer("lossyear", 0, 4)
        self.assertEqual(pedidos[1].get_header("If-match"), '"' + "a"*32 + '"')

    def test_rechaza_etag_o_total_cambiados(self):
        for r in [Respuesta(**{"ETag": '"' + "b"*32 + '"'}),
                  Respuesta(**{"Content-Range": "bytes 0-3/101"})]:
            with tempfile.TemporaryDirectory() as t:
                f = self.abrir_fuente(Respuesta(), t)
                f.leer("lossyear", 0, 4)
                f.abrir = lambda *a, **k: r
                with self.assertRaises(ValueError):
                    f.leer("lossyear", 0, 4)

    def test_graba_y_reproduce_sin_red(self):
        with tempfile.TemporaryDirectory() as t:
            ruta = Path(t) / "cap"
            f = Rangos(ruta, abrir=lambda *a, **k: Respuesta())
            self.assertEqual(f.leer("lossyear", 0, 4), b"abcd")
            f.terminar()
            g = Rangos(ruta, True, abrir=lambda *a, **k: self.fail("red"))
            self.assertEqual(g.leer("lossyear", 0, 4), b"abcd")
            g.terminar()

    def test_captura_modificada_rango_distinto_y_sobrantes(self):
        with tempfile.TemporaryDirectory() as t:
            ruta = Path(t) / "cap"
            f = Rangos(ruta, abrir=lambda *a, **k: Respuesta())
            f.leer("lossyear", 0, 4)
            f.terminar()
            with self.assertRaises(ValueError):
                Rangos(ruta, True).leer("lossyear", 1, 4)
            with self.assertRaises(ValueError):
                Rangos(ruta, True).terminar()
            (ruta / f.registros[0]["archivo"]).write_bytes(b"otra")
            with self.assertRaises(ValueError):
                Rangos(ruta, True).leer("lossyear", 0, 4)

    def test_captura_sobredimensionada_se_lee_con_tope(self):
        with tempfile.TemporaryDirectory() as t:
            ruta = Path(t) / "cap"
            f = Rangos(ruta, abrir=lambda *a, **k: Respuesta())
            f.leer("lossyear", 0, 4)
            f.terminar()
            (ruta / f.registros[0]["archivo"]).write_bytes(b"x"*1_000_000)
            tamanios = []
            original = Path.open
            class Lectura:
                def __enter__(self):
                    return self
                def __exit__(self, *_):
                    pass
                def read(self, n):
                    tamanios.append(n)
                    return b"xxxxx"
            def abrir(path, *a, **k):
                return Lectura() if path.suffix == ".bin" else original(path, *a, **k)
            g = Rangos(ruta, True)
            with patch.object(Path, "open", abrir), self.assertRaises(ValueError):
                g.leer("lossyear", 0, 4)
            self.assertEqual(tamanios, [5])

    def test_recibo_excesivo_no_se_decodifica(self):
        with tempfile.TemporaryDirectory() as t:
            ruta = Path(t)
            (ruta / "rangos.json").write_bytes(b" "*100001)
            with self.assertRaisesRegex(ValueError, "demasiado grande"):
                Rangos(ruta, True)

    def test_cabecera_rechaza_big_tiff_y_truncamiento(self):
        for dato in [b"II+\x00"+b"\x00"*100, b"II*\x00"+struct.pack("<I", 1000),
                     b"II*\x00"+struct.pack("<I", 8)+struct.pack("<H", 100)]:
            with self.assertRaises(ValueError):
                cabecera(dato)

    def test_tiras_lzw_conservan_filas_y_columnas(self):
        tiras, originales = [], []
        for y in [3, 170]:
            original = bytes((x+y) % 256 for x in range(40000))
            originales.append(original)
            memoria = io.BytesIO()
            Image.frombytes("L", (40000, 1), original).save(memoria, format="TIFF",
                                                          compression="tiff_lzw")
            with Image.open(io.BytesIO(memoria.getvalue())) as imagen:
                o, n = imagen.tag_v2[273][0], imagen.tag_v2[279][0]
            tiras.append(memoria.getvalue()[o:o+n])
        for cantidad in [1, 2]:
            with Image.open(io.BytesIO(mini_tiff(tiras[:cantidad]))) as imagen:
                self.assertEqual(imagen.size, (40000, cantidad))
                self.assertEqual(imagen.tobytes(), b"".join(originales[:cantidad]))
                self.assertEqual(imagen.crop((25100, 0, 25110, cantidad)).tobytes(),
                                 b"".join(o[25100:25110] for o in originales[:cantidad]))


class CalculoTest(unittest.TestCase):
    def bandas(self, perdidas, cobertura=None, mascara=None):
        return {"lossyear": bytes(perdidas),
                "treecover2000": bytes(cobertura or [30]*len(perdidas)),
                "datamask": bytes(mascara or [1]*len(perdidas))}

    def test_incluye_30_cero_valido_y_excluye_agua_y_faltante_base(self):
        bandas = self.bandas([24, 24, 24, 25, 0], [29, 30, 100, 30, 30], [1, 1, 2, 0, 1])
        r, capa = calcular(bandas, 25200, 28000, 5, 1, 30, 2024, 2025)
        self.assertEqual(r["conteos_pixeles"], {
            "sin_dato_base": 1, "agua_base": 1, "tierra_base": 3,
            "arbolado_base": 2, "perdida_periodo": 1})
        self.assertEqual(len(capa["features"]), 1)
        self.assertEqual(capa["features"][0]["properties"]["anio"], 2024)
        self.assertIsNone(r["cobertura_observaciones_fraccion"])
        self.assertFalse(r["criterio"]["adoptado"])
        self.assertFalse(r["integrado_en_api"])

    def test_cambia_anios_sin_reutilizar_capa(self):
        b = self.bandas([24, 25, 0])
        a, ca = calcular(b, 25200, 28000, 3, 1, 30, 2024, 2024)
        z, cz = calcular(b, 25200, 28000, 3, 1, 30, 2025, 2025)
        self.assertEqual([f["properties"]["anio"] for f in ca["features"]], [2024])
        self.assertEqual([f["properties"]["anio"] for f in cz["features"]], [2025])
        cero, vacia = calcular(b, 25200, 28000, 3, 1, 30, 2023, 2023)
        self.assertEqual(cero["total_periodo_ha"], 0)
        self.assertEqual(vacia["features"], [])

    def test_bordes_de_pixel_centro_y_area_geodesica(self):
        r, capa = calcular(self.bandas([25]), 25200, 28000, 1, 1, 30, 2025, 2025)
        f = capa["features"][0]
        self.assertEqual(f["id"], "28000-25200")
        anillo = f["geometry"]["coordinates"][0]
        self.assertEqual(anillo[0], [-63.7, -17.0])
        self.assertEqual(anillo[0], anillo[-1])
        self.assertAlmostEqual(anillo[2][0], -63.69975)
        self.assertAlmostEqual(anillo[2][1], -17.00025)
        geod = Geod(ellps="WGS84")
        a, _ = geod.polygon_area_perimeter(*zip(*anillo))
        self.assertAlmostEqual(abs(a)/10000, r["total_periodo_ha"], places=7)
        self.assertNotAlmostEqual(r["total_periodo_ha"], 0.09, places=3)
        ecuador, _ = calcular(self.bandas([25]), 0, 0, 1, 1, 30, 2025, 2025)
        self.assertGreater(ecuador["total_periodo_ha"], r["total_periodo_ha"])

    def test_no_doble_cuenta_ids_y_suma_de_celdas(self):
        r, capa = calcular(self.bandas([24, 25, 24, 25]), 25200, 28000, 2, 2, 30, 2024, 2025)
        self.assertEqual(len({f["id"] for f in capa["features"]}), 4)
        self.assertAlmostEqual(sum(f["properties"]["area_ha"] for f in capa["features"]),
                               r["total_periodo_ha"], places=7)

    def test_rechaza_ventanas_parametros_y_bandas_invalidas(self):
        for v in [(-1, 0, 1, 1), (0, 0, 129, 1), (39999, 0, 2, 1),
                  (0, 0, 1, 0), (True, 0, 1, 1)]:
            with self.subTest(v=v), self.assertRaises(ValueError):
                limites(*v)
        for u, d, h in [(0, 2024, 2025), (True, 2024, 2025), (30, 2025, 2024),
                        (30, 2000, 2024), (30, 2024, 2026)]:
            with self.subTest(u=u, d=d, h=h), self.assertRaises(ValueError):
                calcular(self.bandas([24]), 0, 0, 1, 1, u, d, h)
        for b in [self.bandas([26]), self.bandas([25], [101]),
                  self.bandas([25], [30], [3]), self.bandas([25, 24])]:
            with self.assertRaises(ValueError):
                calcular(b, 0, 0, 1, 1, 30, 2024, 2025)


class CapturaRealTest(unittest.TestCase):
    def test_reproduce_las_tres_bandas_y_geometria_sin_red(self):
        fuente = Rangos(CAPTURA, True, abrir=lambda *a, **k: self.fail("red"))
        bandas = {}
        for banda in BANDAS:
            geo, bandas[banda] = leer_banda(fuente, banda, 25200, 28000, 64, 64)
            self.assertEqual(geo["paso_grados"], 0.00025)
            with Image.open(EVIDENCIA / "resultado" / (banda+".png")) as esperado:
                self.assertEqual(bandas[banda], esperado.tobytes())
        fuente.terminar()
        r, capa = calcular(bandas, 25200, 28000, 64, 64, 30, 2024, 2025)
        guardado = json.loads((EVIDENCIA / "resultado/resumen.json").read_text("utf-8"))
        for campo in r:
            self.assertEqual(r[campo], guardado[campo], campo)
        self.assertEqual(capa, json.loads((EVIDENCIA / "resultado/perdida.geojson").read_text("utf-8")))
        self.assertEqual(fuente.bytes, 1103455)
        self.assertEqual(r["conteos_pixeles"]["perdida_periodo"], 26)

    def test_cli_reproduce_y_no_sobrescribe(self):
        with tempfile.TemporaryDirectory() as t:
            salida = Path(t) / "resultado"
            args = ["--columna", "25200", "--fila", "28000", "--ancho", "64",
                    "--alto", "64", "--umbral", "30", "--desde", "2024", "--hasta", "2025",
                    "--captura", str(CAPTURA), "--salida", str(salida), "--reproducir"]
            with redirect_stdout(io.StringIO()), redirect_stderr(io.StringIO()), \
                    patch("urllib.request.OpenerDirector.open", side_effect=AssertionError("red")):
                self.assertEqual(main(args), 0)
                anterior = (salida / "resumen.json").read_bytes()
                self.assertEqual(main(args), 1)
                self.assertEqual((salida / "resumen.json").read_bytes(), anterior)

    def test_geometria_modificada_es_rechazada(self):
        header = (CAPTURA / "00-lossyear-0-16383.bin").read_bytes()
        with self.assertRaises(ValueError):
            # geometria valida al reunir los tres tags; responder solo el primero no basta.
            g = Rangos(CAPTURA, True)
            g.leer("lossyear", 0, 16384)
            original = g.leer
            def cambiar(banda, inicio, cantidad):
                dato = bytearray(original(banda, inicio, cantidad))
                if inicio == 320218:
                    struct.pack_into("<d", dato, 0, 1/3600)
                return bytes(dato)
            g.leer = cambiar
            geometria(g, "lossyear", cabecera(header))


if __name__ == "__main__":
    unittest.main()
