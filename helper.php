<?php

use Carbon\Carbon;
use Illuminate\Support\Str;

if (!function_exists('path')) {
    /**
     * Выводит путь до каталога с проектом
     * 
     * @param null|string $path
     * @return string
     */
    function path($path = null)
    {
        if (is_string($path) || is_numeric($path)) {
            $path = Str::start($path, "/");
        }

        return __DIR__ . (!empty($path) ? $path : "");
    }
}

if (!function_exists('month')) {
    /**
     * Преобразует короткую строку с месяцем в число
     * 
     * @param string $month
     * @return int
     */
    function month(string $month)
    {
        for ($i = 1; $i <= 12; $i++) {
            $date = Carbon::now()->setMonth($i);
            if (Str::lower($date->clone()->format("M")) == Str::lower($month)) {
                return (int) $date->format("n");
            }
        }

        return match ($month) {
            "янв" => 1,
            "фев" => 2,
            "мар" => 3,
            "апр" => 4,
            "мая" => 5,
            "июн" => 6,
            "июл" => 7,
            "авг" => 8,
            "сен" => 9,
            "окт" => 10,
            "ноя" => 11,
            "дек" => 12,
            default => (int) date("n"),
        };
    }
}
