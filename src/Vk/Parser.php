<?php

namespace Kolgaev\Parser\Vk;

use Kolgaev\Parser\Interfaces\ParserInterface;
use Kolgaev\Parser\Parser as BaseParser;

class Parser extends BaseParser implements ParserInterface
{
    /**
     * Еаименование каталога с кархивом
     * 
     * @var string
     */
    const DIR = "archive-vk";

    /**
     * Игициализация сервиса
     * 
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->dir = path(self::DIR);

        $this->checkFolderArchive($this->dir);
    }

    /**
     * Обработка архива
     * 
     * @return void
     */
    public function handle(): void
    {
        (new Messages($this))->handle();
    }
}