<?php

declare(strict_types=1);

use App\Services\Territorios\CatalogoTerritorial;

require_once dirname(__DIR__, 3).'/web/app/Services/Territorios/FiltroTerritorialInvalido.php';
require_once dirname(__DIR__, 3).'/web/app/Services/Territorios/CatalogoTerritorial.php';

return new CatalogoTerritorial(dirname(__DIR__).'/datos');
