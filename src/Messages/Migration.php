<?php

namespace Kolgaev\VkParser\Messages;

use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;

class Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Manager::schema()->hasTable('vk_messages')) {
            Manager::schema()->create('vk_messages', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('chat_id')->nullable();
                $table->unsignedBigInteger('message_id')->nullable();
                $table->string('user_name')->nullable();
                $table->text('message')->nullable();
                $table->timestamps();
            });
        }
    }
};
