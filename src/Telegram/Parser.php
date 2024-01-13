<?php

namespace Kolgaev\Parser\Telegram;

use Kolgaev\Parser\Interfaces\ParserInterface;
use Kolgaev\Parser\Parser as BaseParser;

class Parser extends BaseParser implements ParserInterface
{
    /**
     * Еаименование каталога с кархивом
     * 
     * @var string
     */
    const DIR = "archive-telegram";

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
        (new Chats($this))->handle();
    }
}