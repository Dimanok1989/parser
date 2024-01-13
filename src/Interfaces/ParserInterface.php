<?php

namespace Kolgaev\Parser\Interfaces;

interface ParserInterface
{
    /**
     * Обработка архива
     * 
     * @return void
     */
    public function handle(): void;
}