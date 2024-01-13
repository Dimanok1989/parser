<?php

namespace Kolgaev\Parser\Interfaces;

use Kolgaev\Parser\Parser;

interface ArchivePartInterface
{
    /**
     * Инициализации парсера раздела
     * 
     * @param \Kolgaev\Parser\Parser $parser
     * @return void
     */
    public function __construct(Parser $parser);
}