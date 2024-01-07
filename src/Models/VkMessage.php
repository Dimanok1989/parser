<?php

namespace Kolgaev\VkParser\Models;

use Illuminate\Database\Eloquent\Model;

class VkMessage extends Model
{
    /**
     * Поля для массового заполнения
     * 
     * @var array
     */
    protected $fillable = [
        'chat_id',
        'message_id',
        'user_name',
        'message',
        'created_at',
    ];
}