"""Deriva los esquemas HTTP de la propuesta acordada #35, sin cambiarla ni descargar datos."""
import copy
import json
import sys
from pathlib import Path

raiz = Path(__file__).resolve().parents[2]
origen = raiz / "compartido/api/sequias-propuesta"
salida = raiz / "compartido/api/sequias"
salida.mkdir(exist_ok=True)

def guardar(nombre, contenido):
    texto = json.dumps(contenido, ensure_ascii=False, indent=2) + "\n"
    if "--check" in sys.argv:
        if (salida / nombre).read_text(encoding="utf-8") != texto:
            raise AssertionError(f"Contrato no reproducible: {nombre}")
    else:
        (salida / nombre).write_text(texto, encoding="utf-8", newline="\n")

consulta = json.loads((origen / "consulta.schema.json").read_text(encoding="utf-8"))
consulta["title"] = "Filtros operativos de sequías 1.0 candidata M3"
guardar("consulta.schema.json", consulta)

r = json.loads((origen / "respuesta.schema.json").read_text(encoding="utf-8"))
r["title"] = "Respuesta HTTP de sequías 1.0 candidata M3"
p = r["properties"]
p["versionContrato"] = {"const": "sequias-1.0-m3-candidata"}
c = p["consulta"]["properties"]
c["estado"] = {"const": "metodo_acordado"}
c["referencia"] = {"const": {"desde": "1991-01-01", "hastaIncluido": "2020-12-31", "ventanaMeses": 3}}
p["consulta"]["required"].append("referencia")
o = c["obtencion"]
o["properties"]["tipo"] = {"const": "cache"}
o["properties"]["esRespuestaHttp"] = {"const": True}
for clave in ("preparadoEn", "vigenteHasta"):
    o["properties"][clave] = {"type": "string", "format": "date-time"}
    o["required"].append(clave)
cob = p["cobertura"]
cob["properties"].update({
    "municipiosConDatos": {"type": "integer", "minimum": 0},
    "muestrasSeleccionadas": {"type": "integer", "minimum": 1, "maximum": 56},
    "muestrasSinPreparar": {"type": "array", "uniqueItems": True, "items": {"type": "string"}},
    "estadoDatos": {"enum": ["completo_para_muestras_seleccionadas", "parcial"]},
})
cob["required"] += ["municipiosConDatos", "muestrasSeleccionadas", "muestrasSinPreparar", "estadoDatos"]
p["datos"]["minItems"] = 1
guardar("respuesta.schema.json", r)
resumen = copy.deepcopy(r)
resumen["title"] = "Resumen HTTP de sequías 1.0 candidata M3"
for clave in ("datos", "paginacion", "representacionMapa"):
    del resumen["properties"][clave]
    resumen["required"].remove(clave)
guardar("resumen.schema.json", resumen)

error = {"type": "object", "required": ["error"], "properties": {"error": {
    "type": "object", "required": ["codigo", "mensaje", "identificadorConsulta", "campos"],
    "properties": {"codigo": {"type": "string"}, "mensaje": {"type": "string"},
                   "identificadorConsulta": {"type": "string"}, "campos": {"type": "array"}}}}}
parametros = [{"name": k, "in": "query", "required": k == "mesFinal", "schema": v}
              for k, v in consulta["properties"].items()]
paths = {}
for ruta, ident, descripcion, esquema in (
    ("/sequias", "consultarSequias", "Muestras preparadas, detalle y resumen del filtro", "respuesta.schema.json"),
    ("/sequias/resumen", "resumirSequias", "Resumen del mismo conjunto sin repetir datos", "resumen.schema.json"),
    ("/sequias/{muestra}", "detalleSequia", "Una muestra registrada con su fuente y SPI", "respuesta.schema.json"),
):
    params = copy.deepcopy(parametros)
    if "{muestra}" in ruta:
        params = [x for x in params if x["name"] != "muestra"]
        params.append({"name": "muestra", "in": "path", "required": True, "schema": {"type": "string"}})
    respuestas = {"200": {"description": descripcion, "content": {"application/json": {"schema": {"$ref": esquema}}}}}
    for codigo in (404, 405, 422, 429, 500, 503):
        respuestas[str(codigo)] = {"description": "Error explícito; sin sustitución por simulación", "content": {"application/json": {"schema": error}}}
    paths[ruta] = {"get": {"operationId": ident, "summary": descripcion, "parameters": params, "responses": respuestas}}
guardar("openapi.json", {"openapi": "3.1.0", "info": {"title": "GeoCruz API de sequías", "version": "1.0-m3-candidata"},
                         "servers": [{"url": "/api/v1"}], "paths": paths})
print("Esquemas y OpenAPI de sequías generados; focos y propuesta #35 conservados.")
