<?php

namespace Kolgaev\Parser;

use DOMDocument;
use Exception;
use Illuminate\Support\Str;
use Symfony\Component\Console\Output\ConsoleOutput;

class Parser
{
    /**
     * Путь до каталога с архивом
     * 
     * @var string
     */
    public $dir;

    /**
     * ConsoleOutput is the default class for all CLI output. It uses STDOUT and STDERR.
     * 
     * @var \Symfony\Component\Console\Output\ConsoleOutput
     */
    protected $output;

    /**
     * Доступные парсеры
     * 
     * @var array
     */
    protected $parsers = [
        'telegram',
        'vk',
    ];

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
        $key = $this->selectParser();

        $class = __NAMESPACE__ . "\\"
            . Str::ucfirst($this->parsers[$key] ?? "Undefined")
            . "\\Parser";

        if (!class_exists($class)) {
            $this->error("Parser [$class] not found");
            return;
        }

        try {
            (new $class)->handle();
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
    }

    /**
     * Выбор сервиса
     * 
     * @return int 
     */
    private function selectParser()
    {
        $selected = null;

        foreach ($_SERVER['argv'] ?? [] as $arg) {

            if ($selected) {
                break;
            }

            foreach ($this->parsers as $key => $parser) {
                if ($arg == "--" . $parser) {
                    $selected = $key;
                    break;
                }
            }
        }

        if ($selected === null) {

            $this->output->writeln("");
            $this->output->writeln("Выберите сервис: ");

            foreach ($this->parsers as $key => $parser) {
                $this->output->writeln("[<fg=green;options=bold>$key</>] " . Str::ucfirst($parser));
            }

            $this->output->writeln("");

            $keys = collect(array_keys($this->parsers));
            $selects = $keys->min() . "-" . $keys->max();

            while ($selected === null) {

                $this->output->write("[$selects]: ");

                $input = (int) trim(fgets(STDIN));

                if (empty($input)) {
                    $input = 0;
                }

                if (isset($this->parsers[$input])) {
                    $selected = $input;
                }
            }

            $this->output->writeln("");
        }

        return $selected;
    }

    /**
     * Проверяет каталог с архивом
     * 
     * @param string $path
     * @return void
     * 
     * @throws \Exception
     */
    public function checkFolderArchive(string $path)
    {
        $dir = pathinfo($path, PATHINFO_BASENAME);

        if (file_exists($path) && is_dir($path)) {

            $files = scandir($path);
            $files = array_diff($files, [".", ".."]);

            if (count($files) == 0) {
                throw new Exception("Каталог с архивом [$dir] пустой");
            }
        } else {
            throw new Exception("Каталог [$dir] не существует");
        }
    }
    
    /**
     * Создает объект документа
     * 
     * @param string $html
     * @return \DOMDocument
     */
    public function getDomDocument(string $html)
    {
        $dom = new DOMDocument;

        $internalErrors = libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_use_internal_errors($internalErrors);

        return $dom;
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

        if (method_exists($this->output, $name)) {
            return $this->output->$name(...$arguments);
        }
    }
}
