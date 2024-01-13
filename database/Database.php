<?php

namespace Kolgaev\Parser\DB;

use Exception;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;
use Symfony\Component\Console\Output\ConsoleOutput;

class Database
{
    /**
     * Конфиги подключения базы данныых
     * 
     * @var array
     */
    protected $config = [];

    /**
     * Инициализация базы данных
     * 
     * @return void
     */
    public function __construct()
    {
        $this->setConfig();

        $manager = new Manager;

        $driver = env('DB_CONNECTION', "mysql");

        if (empty($config = $this->config($driver))) {
            throw new Exception(
                "Неправильный драйвер базы данных [$driver]\n"
                    . "Доступные драйвера [" . collect($this->config)->keys()->join(", ") . "]"
            );
        }

        $manager->addConnection($config);
        $manager->setAsGlobal();
        $manager->bootEloquent();
    }

    /**
     * Настройки базы данных
     * 
     * @param string $driver
     * @return array
     */
    private function config($driver = 'mysql')
    {
        return $this->setConfig()[$driver] ?? null;
    }

    /**
     * Выполнение миграций
     * 
     * @return void
     */
    public function migration()
    {
        $migrations = scandir(__DIR__ . "/migrations");
        $output = new ConsoleOutput;

        $completed = $this->getCompletedMigration();

        foreach ($migrations as $migration) {

            if (in_array($migration, [".", ".."])) {
                continue;
            }

            $filename = pathinfo($migration, PATHINFO_FILENAME);

            if ($completed->search($filename) !== false) {
                continue;
            }

            $migration = include(__DIR__ . "/migrations/$migration");

            if (!is_a($migration, MigrationInterface::class)) {
                throw new Exception("Миграция [{$class}] должна реализовывать интерфейс " . MigrationInterface::class);
            }

            if ($migration->up()) {
                $output->writeln("<fg=green>$filename</> ... migration <fg=green;options=bold>DONE</>");
            }

            Migration::create(['migration' => $filename]);
        }
    }

    /**
     * Выводит завершенные миграции
     * 
     * @return \Illuminate\Http\Collection
     */
    private function getCompletedMigration()
    {
        if (!Manager::schema()->hasTable('migrations')) {
            Manager::schema()->create('migrations', function (Blueprint $table) {
                $table->id();
                $table->string('migration')->comment('Имя файла');
                $table->timestamps();
            });
        }

        return Migration::get()->pluck('migration');
    }

    /**
     * Установка конфига
     * 
     * @return void
     */
    private function setConfig()
    {
        if (empty($this->config)) {
            $this->config = include("config.php");
        }

        return $this->config;
    }
}
