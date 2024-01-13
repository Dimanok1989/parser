<?php

namespace Kolgaev\Parser\DB;

interface MigrationInterface
{
    /**
     * Выполнение миграции
     * 
     * @return bool
     */
    public function up(): bool;
}