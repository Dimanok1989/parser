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
        if (!Manager::schema()->hasTable('message_attachments')) {
            Manager::schema()->create('message_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('message_id')->comment('Идентификатор сообщения')->index();
                $table->string('hash')->nullable()->comment('Хэш файла');
                $table->string('type')->nullable()->comment('Тип вложения');
                $table->string('mime_type')->nullable();
                $table->string('path')->nullable()->comment("Путь до распложения файла в архиве");
                $table->text('content')->nullable()->comment('Содержимое вложения');
                $table->timestamps();
            });

            return true;
        }

        return false;
    }
};
