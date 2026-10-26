<?php

return [
    // El plan conserva cinco módulos; el panel publica solo pantallas funcionales.
    'modulos' => [
        'focos-calor' => [
            'nombre' => 'Focos de calor',
            'descripcion' => 'Detecciones térmicas y su procedencia. Un foco de calor no confirma un incendio.',
            'icono' => 'flame',
            'habilitado' => true,
        ],
        'sequias' => [
            'nombre' => 'Sequías',
            'descripcion' => 'SPI-3 derivado de ERA5, con referencia 1991–2020 y cobertura explícita por muestras.',
            'icono' => 'cloud-rain',
            'habilitado' => true,
        ],
        'inundaciones' => [
            'nombre' => 'Inundaciones',
            'descripcion' => 'Presencia y cambios de agua, diferenciados de una inundación confirmada.',
            'icono' => 'waves',
            'habilitado' => false,
        ],
        'deforestacion' => [
            'nombre' => 'Deforestación',
            'descripcion' => 'Pérdida de cobertura forestal con su producto, definición y año de publicación.',
            'icono' => 'trees',
            'habilitado' => false,
        ],
        'calidad-aire' => [
            'nombre' => 'Calidad del aire',
            'descripcion' => 'Concentraciones disponibles, unidades, fecha y cobertura de cada fuente.',
            'icono' => 'wind',
            'habilitado' => false,
        ],
    ],
];
