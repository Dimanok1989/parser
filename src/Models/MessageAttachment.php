<?php

namespace Kolgaev\Parser\Models;

use Illuminate\Database\Eloquent\Model;

class MessageAttachment extends Model
{
    /**
     * Поля для массового заполнения
     * 
     * @var array
     */
    protected $fillable = [
        'message_id',
        'hash',
        'type',
        'mime_type',
        'path',
        'content',
    ];
}
