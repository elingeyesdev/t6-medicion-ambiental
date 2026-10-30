<?php

namespace Tests\Feature;

use App\Support\ConfiguracionMapa;
use Tests\TestCase;

class ConfiguracionMapaTest extends TestCase
{
    public function test_solo_se_publica_el_token_publico_del_proveedor_fijo(): void
    {
        config(['mapas.token_publico' => 'pk.ejemplo.publico', 'mapas.secreto' => 'no-publicar']);
        $this->assertSame(['proveedor' => 'mapbox', 'tokenPublico' => 'pk.ejemplo.publico', 'baseInicial' => 'claro'], ConfiguracionMapa::paraWeb());
    }

    public function test_una_clave_secreta_o_invalida_no_llega_al_navegador(): void
    {
        foreach ([null, '', 'sk.no-publicar', 'pk.invalido"', "pk.invalido\n", ['pk.no']] as $token) {
            config(['mapas.token_publico' => $token]);
            $this->assertNull(ConfiguracionMapa::paraWeb()['tokenPublico']);
        }
    }
}
