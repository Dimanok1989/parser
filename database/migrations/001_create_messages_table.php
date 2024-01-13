<?php

use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;
use Kolgaev\Parser\DB\MigrationInterface;

return new class implements MigrationInterface
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): bool
    {
        if (!Manager::schema()->hasTable('messages')) {
            Manager::schema()->create('messages', function (Blueprint $table) {
                $table->id();
                $table->string('archive')->nullable()->comment('Источник архива')->index();
                $table->string('chat_id')->nullable()->comment('Идентификатор чата')->index();
                $table->unsignedBigInteger('message_id')->nullable()->comment('Идентификатор сообщения');
                $table->string('from_name')->nullable()->comment('Автор сообщения');
                $table->text('message')->nullable()->comment('Текст сообщения');
                $table->string('forwarded_from_name')->nullable()->comment('Переслано от');
                $table->timestamp('forwarded_created_at')->nullable()->comment('Дата пересылаемого сообщения');
                $table->boolean('is_call')->default(false)->comment('Звонок');
                $table->boolean('is_attach')->default(false)->comment('Сообщение с вложениями');
                $table->timestamps();
            });

            return true;
        }

        return false;
    }
};
