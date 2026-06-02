<?php

namespace App\Services;

use Carbon\Carbon;

class PeruHolidayService
{
    public function forYear(int $year): array
    {
        $easter = $this->easterSunday($year);

        $dates = [
            Carbon::create($year, 1, 1),
            $easter->copy()->subDays(2),
            $easter->copy()->subDay(),
            Carbon::create($year, 5, 1),
            Carbon::create($year, 6, 29),
            Carbon::create($year, 7, 23),
            Carbon::create($year, 7, 28),
            Carbon::create($year, 7, 29),
            Carbon::create($year, 8, 6),
            Carbon::create($year, 8, 30),
            Carbon::create($year, 10, 8),
            Carbon::create($year, 11, 1),
            Carbon::create($year, 12, 8),
            Carbon::create($year, 12, 9),
            Carbon::create($year, 12, 25),
        ];

        $names = [
            'Ano Nuevo',
            'Jueves Santo',
            'Viernes Santo',
            'Dia del Trabajo',
            'San Pedro y San Pablo',
            'Dia de la Fuerza Aerea del Peru',
            'Fiestas Patrias',
            'Fiestas Patrias',
            'Batalla de Junin',
            'Santa Rosa de Lima',
            'Combate de Angamos',
            'Dia de Todos los Santos',
            'Inmaculada Concepcion',
            'Batalla de Ayacucho',
            'Navidad',
        ];

        $map = [];
        foreach ($dates as $i => $date) {
            $map[$date->format('Y-m-d')] = $names[$i];
        }

        return $map;
    }

    private function easterSunday(int $year): Carbon
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $leapOffset = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $leapOffset, 451);
        $month = intdiv($h + $leapOffset - 7 * $m + 114, 31);
        $day = (($h + $leapOffset - 7 * $m + 114) % 31) + 1;

        return Carbon::create($year, $month, $day);
    }
}
