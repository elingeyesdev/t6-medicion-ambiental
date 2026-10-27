# Mapa de la portada

`santa-cruz.svg` se genera con `herramientas/preparar-mapa-inicio.py` a partir de las quince provincias de `compartido/territorios/datos/limites/provincias.geojson`. Se reducen segmentos menores de un píxel para la portada. La API y los visores mantienen las geometrías del catálogo sin este ajuste visual.

Fuente: Ministerio de Desarrollo Rural y Tierras / OCHA FIS, distribución OCHA/HDX, versión v02. Licencia CC BY 3.0 IGO, documentada en el manifiesto y README del catálogo. Es contexto geográfico de referencia, sin observaciones ni pronósticos ambientales. No es una imagen generada con IA ni un deslinde legal.

La estructura de inicio y acceso adapta `LandingPage`, `LandingHeader`, `LandingHero`, `AuthHero` y `Auth.css` de la referencia conservada de EnviroSense. No se reutilizan las cifras globales ni las imágenes con lecturas meteorológicas ilustrativas del referente. La marca conserva su símbolo original. Controles y formularios: Tabler 1.7.1.
