<?php

namespace Kolgaev\VkParser;

use Exception;
use Kolgaev\VkParser\Messages\Messages;
use Symfony\Component\Console\Output\ConsoleOutput;

class Parser
{
    /**
     * Путь до каталога с архивом
     * 
     * @var string
     */
    const ARCHIVE_DIR = __DIR__ . "/../archive";

    /**
     * @var \Symfony\Component\Console\Output\ConsoleOutput
     */
    protected $output;

    /**
     * Инициализация парсера
     * 
     * @return void
     */
    public function __construct()
    {
        $this->output = new ConsoleOutput;
    }

    /**
     * Метод, запускаемый при выхове из консоли
     * 
     * @return void
     */
    public function __invoke()
    {
        try {
            (new Messages)->index();
        } catch (Exception $e) {
            $this->output->write("<fg=red>" . $e->getMessage() . "</>");
        }
    }

    /**
     * Обработка несуществующих методов
     * 
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if ($name == "line") {
            $arguments = empty($arguments) ? [""] : $arguments;
            return $this->output->writeln(...$arguments);
        }

        if (in_array($name, ['info', 'comment', 'error'])) {
            $arguments[0] = "<{$name}>" . ($arguments[0] ?? "") . "</{$name}>";
            return $this->output->writeln(...$arguments);
        }
    }
}
