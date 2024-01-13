<?php

namespace Kolgaev\Parser\DB;

use Illuminate\Database\Eloquent\Model;

class Migration extends Model
{
    /**
     * Поля для массового заполнения
     * 
     * @var array
     */
    protected $fillable = [
        'migration',
    ];
}
