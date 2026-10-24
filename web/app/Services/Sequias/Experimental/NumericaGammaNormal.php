<?php

declare(strict_types=1);

namespace App\Services\Sequias\Experimental;

use RuntimeException;

/** Núcleo acotado de contraste; ecuaciones y tolerancias en API02_ENSAYO_SPI.md. */
final class NumericaGammaNormal
{
    private const EPS = 2e-14;

    private const ITERACIONES = 10000;

    public function ajustar(array $positivos): array
    {
        // Escalar por el máximo evita desbordar al sumar valores finitos grandes.
        $maximo = max($positivos);
        $normalizados = array_map(fn ($v) => $v / $maximo, $positivos);
        $media = $this->sumar($normalizados) / count($positivos);
        $s = log($media) - $this->sumar(array_map(log(...), $normalizados)) / count($positivos);
        if (! is_finite($s) || $s <= 0) {
            throw new RuntimeException('Ajuste no representable.');
        }
        // log(a)-digamma(a) es decreciente. Cota técnica explícita, no climatológica.
        $inferior = 1e-8;
        $superior = 1e6;
        if ($this->ecuacionForma($inferior) < $s || $this->ecuacionForma($superior) > $s) {
            throw new RuntimeException('Forma fuera del rango numérico contrastado.');
        }
        for ($i = 0; $i < 160; $i++) {
            $medio = ($inferior + $superior) / 2;
            if ($this->ecuacionForma($medio) > $s) {
                $inferior = $medio;
            } else {
                $superior = $medio;
            }
            if ($superior - $inferior <= self::EPS * $medio) {
                $forma = ($inferior + $superior) / 2;
                $escala = ($media / $forma) * $maximo;
                if (is_finite($escala) && $escala > 0) {
                    return [$forma, $escala];
                }
                break;
            }
        }
        throw new RuntimeException('Ajuste no convergente.');
    }

    /** P(a,x), Q(a,x); conservar Q evita restar casi uno en la inversa normal. */
    public function gamma(float $a, float $x): array
    {
        if (! is_finite($a) || $a <= 0 || ! is_finite($x) || $x < 0) {
            throw new RuntimeException('Argumentos Gamma no representables.');
        }
        if ($x === 0.0) {
            return [0.0, 1.0];
        }
        $factor = exp($a * log($x) - $x - $this->logGamma($a));
        if ($x < $a + 1) {
            // Serie positiva de gamma inferior, DLMF 8.7.1.
            $termino = 1 / $a;
            $suma = $termino;
            for ($n = 1; $n <= self::ITERACIONES; $n++) {
                $termino *= $x / ($a + $n);
                $suma += $termino;
                if (abs($termino) <= abs($suma) * self::EPS) {
                    return $this->probabilidades($factor * $suma, true);
                }
            }
        } else {
            // Fracción continua de Gamma superior, evaluación de Lentz modificada.
            $b = $x + 1 - $a;
            $c = 1e300;
            $d = 1 / $this->noCero($b);
            $h = $d;
            for ($n = 1; $n <= self::ITERACIONES; $n++) {
                $an = -$n * ($n - $a);
                $b += 2;
                $d = 1 / $this->noCero($an * $d + $b);
                $c = $this->noCero($b + $an / $c);
                $delta = $d * $c;
                $h *= $delta;
                if (abs($delta - 1) <= self::EPS) {
                    return $this->probabilidades($factor * $h, false);
                }
            }
        }
        throw new RuntimeException('Gamma no convergente.');
    }

    public function normalInversa(float $p): float
    {
        if (! is_finite($p) || $p <= 0 || $p >= 1) {
            throw new RuntimeException('Probabilidad normal fuera del intervalo abierto.');
        }
        if ($p === 0.5) {
            return 0.0;
        }
        $cola = min($p, 1 - $p);
        $inferior = 0.0;
        $superior = 40.0;
        // Phi(-z)=Q(1/2,z²/2)/2. Bisección acotada, sin coeficientes de otra biblioteca.
        for ($i = 0; $i < 80; $i++) {
            $z = ($inferior + $superior) / 2;
            [, $q] = $this->gamma(0.5, $z * $z / 2);
            if ($q / 2 > $cola) {
                $inferior = $z;
            } else {
                $superior = $z;
            }
        }

        return ($p < 0.5 ? -1 : 1) * (($inferior + $superior) / 2);
    }

    private function ecuacionForma(float $a): float
    {
        $z = $a;
        $recurrencia = 0.0;
        while ($z < 12) {
            $recurrencia += 1 / $z;
            $z++;
        }
        $r = 1 / ($z * $z);
        $correccion = 1 / (2 * $z) + $r * (1 / 12 + $r * (-1 / 120 + $r * (1 / 252 + $r * (-1 / 240 + $r * (1 / 132 + $r * (-691 / 32760))))));

        return log($a / $z) + $correccion + $recurrencia;
    }

    private function logGamma(float $a): float
    {
        $z = $a;
        $recurrencia = 0.0;
        while ($z < 12) {
            $recurrencia += log($z);
            $z++;
        }
        $r = 1 / ($z * $z);
        $correccion = (1 / 12 + $r * (-1 / 360 + $r * (1 / 1260 + $r * (-1 / 1680 + $r * (1 / 1188 + $r * (-691 / 360360)))))) / $z;

        return ($z - 0.5) * log($z) - $z + 0.5 * log(2 * M_PI) + $correccion - $recurrencia;
    }

    private function probabilidades(float $valor, bool $inferior): array
    {
        if (! is_finite($valor) || $valor < 0 || $valor > 1) {
            throw new RuntimeException('Probabilidad Gamma no representable.');
        }

        return $inferior ? [$valor, 1 - $valor] : [1 - $valor, $valor];
    }

    private function noCero(float $valor): float
    {
        return abs($valor) < 1e-300 ? ($valor < 0 ? -1e-300 : 1e-300) : $valor;
    }

    private function sumar(array $valores): float
    {
        $suma = 0.0;
        $error = 0.0;
        foreach ($valores as $valor) {
            $v = $valor - $error;
            $nuevo = $suma + $v;
            $error = ($nuevo - $suma) - $v;
            $suma = $nuevo;
        }

        return $suma;
    }
}
