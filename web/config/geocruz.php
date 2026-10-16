<?php

return [
    // Navegación inicial de DOC-11. Estas entradas no habilitan consultas.
    'modulos' => [
        'focos-calor' => [
            'nombre' => 'Focos de calor',
            'descripcion' => 'Detecciones térmicas y su procedencia. Un foco de calor no confirma un incendio.',
            'icono' => '01',
        ],
        'sequias' => [
            'nombre' => 'Sequías',
            'descripcion' => 'Variables ambientales e indicadores con su método y período de referencia.',
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
