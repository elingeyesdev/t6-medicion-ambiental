<?php

return [
    // Navegación común. Cada pantalla informa su disponibilidad y cobertura real.
    'modulos' => [
        'focos-calor' => [
            'nombre' => 'Focos de calor',
            'descripcion' => 'Detecciones térmicas y su procedencia. Un foco de calor no confirma un incendio.',
            'icono' => '01',
        ],
        'sequias' => [
            'nombre' => 'Sequías',
            'descripcion' => 'SPI-3 derivado de ERA5, con referencia 1991–2020 y cobertura explícita por muestras.',
            'icono' => '02',
        ],
        'inundaciones' => [
            'nombre' => 'Inundaciones',
            'descripcion' => 'Presencia y cambios de agua, diferenciados de una inundación confirmada.',
            'icono' => '03',
        ],
        'deforestacion' => [
            'nombre' => 'Deforestación',
            'descripcion' => 'Pérdida de cobertura forestal con su producto, definición y año de publicación.',
            'icono' => '04',
        ],
        'calidad-aire' => [
            'nombre' => 'Calidad del aire',
            'descripcion' => 'Concentraciones disponibles, unidades, fecha y cobertura de cada fuente.',
            'icono' => '05',
        ],
    ],
];
