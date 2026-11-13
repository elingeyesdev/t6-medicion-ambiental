"""Ejecuta la renovación de Sequías desde el Programador de tareas de Windows."""

import argparse
import subprocess
from pathlib import Path


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--php", required=True, type=Path)
    parser.add_argument("--ini", required=True, type=Path)
    parser.add_argument("--ahora", action="store_true")
    args = parser.parse_args()
    web = Path(__file__).resolve().parents[2] / "web"
    registro = web / "storage" / "logs" / "sequias-tarea.log"
    comando = [str(args.php), "-c", str(args.ini), "artisan",
               "sequias:renovar-piloto", "--no-interaction"]
    if args.ahora:
        comando.append("--ahora")
    with registro.open("wb") as salida:
        try:
            resultado = subprocess.run(
                comando, cwd=web, stdin=subprocess.DEVNULL, stdout=salida,
                stderr=subprocess.STDOUT, timeout=110,
                creationflags=subprocess.CREATE_NO_WINDOW, check=False,
            )
        except (OSError, subprocess.TimeoutExpired) as error:
            salida.write(f"{error}\n".encode("utf-8"))
            return 1
        return resultado.returncode


if __name__ == "__main__":
    raise SystemExit(main())
