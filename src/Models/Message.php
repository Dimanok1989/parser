<?php

namespace Kolgaev\Parser\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    /**
     * Поля для массового заполнения
     * 
     * @var array
     */
    protected $fillable = [
        'archive',
        'chat_id',
        'message_id',
        'from_name',
        'message',
        'forwarded_from_name',
        'forwarded_created_at',
        'is_call',
        'is_attach',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_call' => "boolean",
        'is_attach' => "boolean",
        'forwarded_created_at' => "datetime",
    ];
}
