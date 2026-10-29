<?php

// Broker de contrato: solo loopback efímero, sin credenciales, dispositivos ni dependencias.
$servidor = stream_socket_server('tcp://127.0.0.1:0', $codigo, $error);
if (! $servidor) {
    exit(2);
}
echo stream_socket_get_name($servidor, false)."\n";
flush();
$socket = stream_socket_accept($servidor, 5);
if (! $socket) {
    exit(3);
}
stream_set_timeout($socket, 3);
function leer($socket, int $n): string
{
    $s = '';
    while (strlen($s) < $n) {
        $p = fread($socket, $n - strlen($s));
        if ($p === '' || $p === false) {
            throw new RuntimeException('Fin de recepción.');
        }
        $s .= $p;
    }

    return $s;
}
function paquete($socket): array
{
    $tipo = ord(leer($socket, 1));
    $n = 0;
    $m = 1;
    do {
        $b = ord(leer($socket, 1));
        $n += ($b & 127) * $m;
        $m *= 128;
    } while ($b & 128);

    return [$tipo, leer($socket, $n)];
}
function enviar($socket, int $tipo, string $cuerpo): void
{
    $n = strlen($cuerpo);
    $cabecera = chr($tipo);
    do {
        $b = $n % 128;
        $n = intdiv($n, 128);
        $cabecera .= chr($b | ($n ? 128 : 0));
    } while ($n);
    // Fuerza lecturas parciales incluso con paquetes pequeños.
    fwrite($socket, $cabecera[0]);
    usleep(3000);
    fwrite($socket, substr($cabecera, 1).$cuerpo);
}
$resultado = ['acks' => [], 'connect' => false, 'subscribe' => false];
$modo = $argv[1] ?? 'normal';
try {
    [$tipo, $cuerpo] = paquete($socket);
    $resultado['connect'] = $tipo === 0x10 && substr($cuerpo, 0, 7) === "\0\4MQTT\4" && (ord($cuerpo[7]) & 2) === 0;
    if ($modo === 'conexion-rechazada') {
        enviar($socket, 0x20, "\0\5");
    } else {
        enviar($socket, 0x20, "\0\0");
        [$tipo, $cuerpo] = paquete($socket);
        $resultado['subscribe'] = $tipo === 0x82 && str_contains($cuerpo, 'geosur/ejemplo/temperatura') && str_ends_with($cuerpo, "\1");
        $tema = $modo === 'tema-ajeno' ? 'otro/tema' : 'geosur/ejemplo/temperatura';
        $contenido = $modo === 'mensaje-invalido' ? '{"valor":61,"instante":"2026-10-27T12:00:00Z"}' : '{"valor":25.5,"instante":"2026-10-27T12:00:00Z"}';
        $publicacion = pack('n', strlen($tema)).$tema.pack('n', 10).$contenido;
        if ($modo === 'antes-suback') {
            enviar($socket, 0x32, $publicacion);
            [$tipo, $cuerpo] = paquete($socket);
            if ($tipo === 0x40) {
                $resultado['acks'][] = unpack('n', $cuerpo)[1];
            }
        }
        if ($modo === 'sin-suback') {
            // No responde: el cliente debe terminar por su límite.
        } else {
            enviar($socket, 0x90, "\0\1".($modo === 'suscripcion-rechazada' ? "\x80" : "\1"));
            if ($modo === 'truncado') {
                fwrite($socket, "\x32\x08\x00");
            } elseif ($modo === 'excesivo') {
                fwrite($socket, "\x32\xFF\x7F");
            } elseif ($modo !== 'suscripcion-rechazada' && $modo !== 'antes-suback') {
                enviar($socket, $modo === 'qos2' ? 0x34 : 0x33, $publicacion);
            }
        }
        if ($modo === 'truncado' || $modo === 'excesivo') {
            stream_socket_shutdown($socket, STREAM_SHUT_WR);
        }
    }
    $reenviado = false;
    while (true) {
        [$tipo, $cuerpo] = paquete($socket);
        if ($tipo === 0x40) {
            $resultado['acks'][] = unpack('n', $cuerpo)[1];
            if ($modo === 'normal' && ! $reenviado) {
                enviar($socket, 0x3A, $publicacion);
                $reenviado = true;
            }
        } elseif ($tipo === 0xE0) {
            break;
        } elseif ($tipo === 0xC0) {
            enviar($socket, 0xD0, '');
        }
    }
} catch (RuntimeException) {
    // Rechazo o cierre esperado: el registro de ACK sigue siendo verificable.
} finally {
    fclose($socket);
    fclose($servidor);
}
echo json_encode($resultado, JSON_THROW_ON_ERROR)."\n";
