<?php

namespace Kolgaev\VkParser;

use Illuminate\Database\Capsule\Manager;
use PDO;

class Database
{
    /**
     * Инициализация базы данных
     * 
     * @return void
     */
    public function __construct()
    {
        $manager = new Manager;

        $manager->addConnection([
            'driver' => env('DB_CONNECTION', "mysql"),
            'host' => env('DB_HOST', "localhost"),
            'port' => env('DB_PORT', "localhost"),
            'database' => env('DB_DATABASE', "database"),
            'username' => env('DB_USERNAME', "root"),
            'password' => env('DB_PASSWORD', ""),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ]);

        $manager->setAsGlobal();
        $manager->bootEloquent();
    }
}
