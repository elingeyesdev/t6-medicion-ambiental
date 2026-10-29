<?php

return json_decode(file_get_contents(base_path('../compartido/unidades/presentacion.json')), true, 512, JSON_THROW_ON_ERROR)['grupos'];
