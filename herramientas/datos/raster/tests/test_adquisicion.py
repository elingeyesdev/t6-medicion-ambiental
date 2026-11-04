import sys
from pathlib import Path
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from adquirir_gfs import descargar, parametros


class Respuesta:
    status = 200

    def __init__(self, contenido, estado=200, largo=None):
        self.contenido, self.status, self.largo = contenido, estado, largo

    def getheader(self, clave, defecto=None):
        return str(self.largo) if clave == "Content-Length" and self.largo is not None else defecto

    def read1(self, largo):
        trozo, self.contenido = self.contenido[:largo], self.contenido[largo:]
        return trozo


class Conexion:
    def __init__(self, respuesta):
        self.respuesta, self.sock, self.solicitudes, self.cerrada = respuesta, self, [], False

    def connect(self):
        pass

    def settimeout(self, valor):
        if not 0 < valor <= 30:
            raise AssertionError("El límite de espera se perdió")

    def request(self, metodo, ruta, headers):
        self.solicitudes.append((metodo, ruta))

    def getresponse(self):
        return self.respuesta

    def close(self):
        self.cerrada = True


class AdquisicionTest(unittest.TestCase):
    def probar(self, respuesta):
        conexion = Conexion(respuesta)
        def crear(host, timeout, context):
            self.assertEqual(host, "nomads.ncep.noaa.gov")
            self.assertEqual(timeout, 30)
            self.assertTrue(context.check_hostname)
            return conexion
        resultado = descargar("2026102906", 0, crear)
        self.assertEqual(len(conexion.solicitudes), 1)
        self.assertTrue(conexion.cerrada)
        return resultado

    def test_mensaje_completo_y_recibo_sin_cambiar_proveedor(self):
        mensaje = b"GRIB\0\0\0\2" + (20).to_bytes(8, "big") + b"7777"
        datos, recibo = self.probar(Respuesta(mensaje, largo=len(mensaje)))
        self.assertEqual(datos, mensaje)
        self.assertEqual(recibo["estado"], "descargado")
        self.assertEqual(recibo["bytes"], 20)

    def test_html_redireccion_truncado_y_exceso_no_son_datos(self):
        for r in (Respuesta(b"<html>No disponible</html>"), Respuesta(b"", 302),
                  Respuesta(b"GRIB", largo=100), Respuesta(b"", largo=2000001),
                  Respuesta(b"x" * 2000001)):
            with self.subTest(estado=r.status, largo=r.largo):
                datos, recibo = self.probar(r)
                self.assertIsNone(datos)
                self.assertEqual(recibo["estado"], "fallido")

    def test_ciclo_y_plazo_explicitos(self):
        for ciclo, plazo in (("2026102905", 0), ("2026023006", 0), ("2026102906", -1), ("2026102906", 121)):
            with self.assertRaises(ValueError):
                parametros(ciclo, plazo)


if __name__ == "__main__":
    unittest.main()
