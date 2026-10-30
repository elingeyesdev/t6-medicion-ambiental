"""Actualización GFS acotada: dos recortes, ocho capas y publicación local completa."""
import argparse
from contextlib import contextmanager
from datetime import datetime, timedelta, timezone
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import time
import uuid

from adquirir_gfs import descargar


def utc(ahora):
    if ahora.tzinfo is None:
        raise ValueError('La hora de actualización debe tener zona horaria.')
    return ahora.astimezone(timezone.utc)


def ciclo_disponible(ahora):
    # Margen propio de seis horas; no se promete una hora de publicación del proveedor.
    fecha = utc(ahora) - timedelta(hours=6)
    return fecha.replace(hour=fecha.hour // 6 * 6, minute=0, second=0, microsecond=0).strftime('%Y%m%d%H')


def escribir_json(ruta, datos):
    if ruta.is_symlink():
        raise ValueError('No se sobrescribe un enlace como estado de actualización.')
    temporal = None
    try:
        with tempfile.NamedTemporaryFile(mode='w', encoding='utf-8', dir=ruta.parent, delete=False) as salida:
            temporal = Path(salida.name)
            json.dump(datos, salida, ensure_ascii=False, allow_nan=False, indent=2)
            salida.write('\n')
            salida.flush()
            os.fsync(salida.fileno())
        os.replace(temporal, ruta)
    finally:
        if temporal and temporal.exists():
            temporal.unlink()


class Ocupado(Exception):
    pass


@contextmanager
def bloqueo(ruta):
    if ruta.is_symlink():
        raise ValueError('Bloqueo de actualización inválido.')
    with ruta.open('a+b') as archivo:
        archivo.seek(0, 2)
        if archivo.tell() == 0:
            archivo.write(b'0')
            archivo.flush()
        archivo.seek(0)
        try:
            if os.name == 'nt':
                import msvcrt
                msvcrt.locking(archivo.fileno(), msvcrt.LK_NBLCK, 1)
            else:
                import fcntl
                fcntl.flock(archivo, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except OSError as error:
            raise Ocupado('Ya hay una actualización en marcha.') from error
        try:
            yield
        finally:
            archivo.seek(0)
            if os.name == 'nt':
                msvcrt.locking(archivo.fileno(), msvcrt.LK_UNLCK, 1)
            else:
                fcntl.flock(archivo, fcntl.LOCK_UN)
            # No borrar este archivo: todos los procesos deben bloquear el mismo archivo.


def producir(entrada, salida):
    from campos_gfs import CAPAS, producir_campos
    return producir_campos(entrada, salida, list(CAPAS), acumulado_desde=0)


def actualizar(trabajo, publicar, ahora=None, adquirir=descargar, transformar=producir, esperar=time.sleep):
    ahora = utc(ahora or datetime.now(timezone.utc))
    trabajo = Path(trabajo)
    trabajo.mkdir(parents=True, exist_ok=True)
    if trabajo.is_symlink() or not trabajo.is_dir():
        raise ValueError('Revisa el directorio privado de trabajo.')
    ciclo = ciclo_disponible(ahora)
    with bloqueo(trabajo/'actualizacion.lock'):
        ruta_estado = trabajo/'estado.json'
        anterior = {}
        if ruta_estado.exists():
            if ruta_estado.is_symlink() or ruta_estado.stat().st_size > 65536:
                raise ValueError('Estado de actualización inválido.')
            anterior = json.loads(ruta_estado.read_text(encoding='utf-8'))
            if not isinstance(anterior, dict):
                raise ValueError('Estado de actualización inválido.')
        if anterior.get('ultimo_ciclo_publicado', '') >= ciclo:
            return {'estado': 'al_dia', 'ciclo': ciclo}
        if anterior.get('intento_utc'):
            intento = datetime.fromisoformat(anterior['intento_utc'])
            if ahora - utc(intento) < timedelta(hours=1):
                return {'estado': 'esperando', 'ciclo': ciclo}
        anterior.pop('error', None)
        estado = {**anterior, 'estado': 'actualizando', 'ciclo': ciclo, 'intento_utc': ahora.isoformat()}
        escribir_json(ruta_estado, estado)
        corrida = trabajo/(ciclo+'-'+uuid.uuid4().hex)
        corrida.mkdir()
        lote = corrida/'lote'
        lote.mkdir()
        cuadros, recibos = [], []
        try:
            for indice, plazo in enumerate((3, 6)):
                if indice:
                    esperar(10)  # NOMADS exige al menos diez segundos entre peticiones de bucles.
                contenido, recibo = adquirir(ciclo, plazo, conjunto=True)
                escribir_json(corrida/f'recibo-{plazo:03d}.json', recibo)
                if contenido is None:
                    raise ValueError('No se pudo descargar el conjunto completo; se conserva la publicación anterior.')
                entrada = corrida/f'campos-{plazo:03d}.grib2'
                entrada.write_bytes(contenido)
                salida = corrida/f'campos-{plazo:03d}'
                metadata = transformar(entrada, salida)
                esperado = datetime.strptime(ciclo, '%Y%m%d%H').replace(tzinfo=timezone.utc)
                if metadata.get('datos_controlados') is not False or any(
                    campo['tiempo']['ciclo'] != esperado.strftime('%Y-%m-%dT%H:%M:%SZ') or
                    campo['tiempo']['fin_horas'] != plazo for campo in metadata['campos']
                ):
                    raise ValueError('El conjunto recibido no corresponde al ciclo y plazo pedidos.')
                receta = json.loads((salida/'receta.json').read_text(encoding='utf-8'))
                for cuadro in receta['cuadros']:
                    relativa = Path(cuadro['archivo'])
                    origen = salida/relativa
                    if relativa.is_absolute() or '..' in relativa.parts or not origen.resolve().is_relative_to(salida.resolve()):
                        raise ValueError('La receta sale del lote producido.')
                    destino = lote/relativa
                    destino.parent.mkdir(parents=True, exist_ok=True)
                    with origen.open('rb') as fuente, destino.open('xb') as archivo:
                        shutil.copyfileobj(fuente, archivo)
                cuadros.extend(receta['cuadros'])
                recibos.append(recibo)
            escribir_json(lote/'receta.json', {'version': 1, 'cuadros': cuadros})
            escribir_json(lote/'actualizacion.json', {'version': 1, 'ciclo': ciclo, 'datos_controlados': False, 'recibos': recibos})
            resultado = publicar(lote)
            if resultado.get('estado') != 'publicado':
                raise ValueError('La publicación no confirmó el conjunto completo.')
            escribir_json(ruta_estado, {**estado, 'estado': 'publicado', 'ultimo_ciclo_publicado': ciclo,
                                       'publicado_utc': datetime.now(timezone.utc).isoformat(), 'resultado': resultado})
            return resultado
        except Exception as error:
            escribir_json(ruta_estado, {**estado, 'estado': 'fallido', 'error': str(error)[:1000]})
            raise


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--trabajo', required=True)
    parser.add_argument('--php', required=True)
    parser.add_argument('--php-ini')
    parser.add_argument('--artisan', required=True)
    parser.add_argument('--consultar-noaa', action='store_true')
    args = parser.parse_args()
    if not args.consultar_noaa:
        parser.error('La adquisición requiere --consultar-noaa.')

    def publicar(lote):
        comando = [args.php] + (['-c', args.php_ini] if args.php_ini else []) + [args.artisan, 'capas:incorporar', str(lote), '--no-interaction']
        proceso = subprocess.run(comando, capture_output=True, text=True, encoding='utf-8', timeout=60, check=False)
        if proceso.returncode:
            raise ValueError('No se pudo incorporar el lote. '+proceso.stdout.strip()[:300])
        return json.loads(proceso.stdout)

    try:
        resultado = actualizar(args.trabajo, publicar)
        print(json.dumps(resultado, ensure_ascii=False))
        return 0
    except Ocupado:
        print(json.dumps({'estado': 'ocupado'}))
        return 0
    except (ValueError, OSError, RuntimeError, subprocess.SubprocessError, KeyError) as error:
        print(json.dumps({'estado': 'fallido', 'mensaje': str(error)}, ensure_ascii=False))
        return 1


if __name__ == '__main__':
    sys.exit(main())
