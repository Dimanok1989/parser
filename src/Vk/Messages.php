<?php

namespace Kolgaev\Parser\Vk;

use Carbon\Carbon;
use DOMXPath;
use Exception;
use Illuminate\Support\Str;
use Kolgaev\Parser\Interfaces\ArchivePartInterface;
use Kolgaev\Parser\Interfaces\ParserInterface;
use Kolgaev\Parser\Models\Message;
use Kolgaev\Parser\Parser;

class Messages implements ArchivePartInterface, ParserInterface
{
    /**
     * Каталог с данными
     * 
     * @var string
     */
    const DIR = "messages";

    /**
     * Путь до каталога с данными
     * 
     * @var string
     */
    protected $dir;

    /**
     * Файл главной страницы со списком контактов
     * 
     * @var string
     */
    protected $index;

    /**
     * Список найденных контактов
     * 
     * @var \Illuminate\Support\Collection
     */
    protected $contacts;

    /**
     * Счетчик сообщений
     * 
     * @var int
     */
    protected $count = 0;

    /**
     * Счетчик созданных сообщений
     * 
     * @var int
     */
    protected $countMessageCreated = 0;

    /**
     * Счетчик обновленных сообщений
     * 
     * @var int
     */
    protected $countMessageUpdated = 0;

    /**
     * Страницы с данными
     * 
     * @var \Illuminate\Support\Collection
     */
    protected $pages;

    /**
     * Текущая страница к обработке
     * 
     * @var int
     */
    protected $page = 1;

    /**
     * Текущая страница к обработке
     * 
     * @var \Illuminate\Support\Collection
     */
    protected $args;

    /**
     * Инициализации парсера раздела
     * 
     * @param \Kolgaev\Parser\Parser $parser
     * @return void
     */
    public function __construct(protected Parser $parser)
    {
        $this->args = collect($_SERVER['argv'] ?? []);

        $this->dir = env('ARCHIVE_VK', $this->parser->dir) . "/" . self::DIR;
        $this->index = $this->dir . "/index-messages.html";

        if (!file_exists($this->index)) {
            throw new Exception("File {$this->index} not found");
        }
    }

    /**
     * Обработка архива
     * 
     * @return void
     */
    public function handle(): void
    {
        $this->index();
    }

    /**
     * Main messages page
     * 
     * @return void
     */
    public function index()
    {
        $indexHtml = file_get_contents($this->index);
        $dom = $this->parser->getDomDocument($indexHtml);
        $xpath = new DOMXPath($dom);

        $items = $xpath->query('//div[contains(@class, "message-peer--id")]');

        foreach ($items as $item) {

            foreach ($item->getElementsByTagName('a') as $a) {
                $contacts[] = [
                    'path' => $a->getAttribute('href'),
                    'name' => trim($a->textContent ?? ""),
                    'chatId' => (int) pathinfo($a->getAttribute('href'), PATHINFO_DIRNAME),
                ];
            }
        }

        $this->contacts = collect($contacts)->sortBy('name')->values();

        $this->selectContact();
    }

    /**
     * Выбор контакта
     * 
     * @return void
     */
    private function selectContact()
    {
        $item = null;

        $chat = $this->args
            ->map(
                fn ($item) => Str::startsWith($item, '--chat=') ? $item : null
            )
            ->filter()
            ->first();

        if ($chat) {
            if (is_numeric($selectId = explode("=", $chat)[1] ?? null)) {
                $item = (int) $selectId;
            }
        }

        if ($item !== null && empty($this->contacts[$item])) {
            $item = null;
        }

        if ($item === null) {

            $this->parser->line();

            foreach ($this->contacts as $key => $contact) {
                $this->parser->line("[<fg=green;options=bold>$key</>] {$contact['name']}");
            }

            $this->parser->line();

            $keys = collect($this->contacts)->keys();
            $keyString = $keys->min() . "-" . $keys->max();

            while ($item === null) {
                $this->parser->write("Выберите идентификатор пользователя [$keyString]: ");
                $line_host = trim(fgets(STDIN));
                $item = (int) $line_host;
            }
        }

        if (empty($this->contacts[$item])) {
            throw new Exception("Not fount item [$item] contacts collection");
        }

        $contact = $this->contacts[$item];

        $this->parser->line();
        $this->parser->line("<fg=blue;options=bold>{$contact['name']}</>");
        $this->parser->line($contact['chatId']);
        $this->parser->line();

        $this->getMessages($contact);

        $this->parser->line("\n");
        $this->parser->line("Найдено сообщений: " . $this->count);

        $write = $this->args->search("-Y") !== false ? "Y" : false;

        while (!$write) {
            $this->parser->write("Сохранить их в базу данных? (y/Y/n/N) [Y] ");
            $line_update = trim(fgets(STDIN));
            if (empty($line_update)) {
                $line_update = "Y";
            }
            $write = $line_update;
        }

        if (in_array($write, ['y', 'Y', 'д', 'Д'])) {
            $contact['write'] = true;
            $write = true;
        } else {
            $write = false;
        }

        if (!$write) {
            $this->parser->line("<fg=yellow>Остановлено!</>");
        } else {

            $this->parser->info("\nЗапись в базу данных:");

            $this->pages
                ->sortByDesc('page')
                ->values()
                ->each(function ($page) use ($contact) {

                    $contact['path'] = $page['href']
                        ?? $contact['chatId'] . "/messages0.html";

                    // dump($contact);
                    $this->getMessages($contact);
                });

            $this->parser->line("\n");
            $this->parser->line("<fg=green>Создано сообщений</>: <options=bold>{$this->countMessageCreated}</>");
            $this->parser->line("<fg=yellow>Обновлено сообщений</>: <options=bold>{$this->countMessageUpdated}</>");
            $this->parser->line("");
        }
    }

    /**
     * Поиск сообщений
     * 
     * @param array
     * @return void
     */
    public function getMessages($contact)
    {
        $messages = [];

        $html = file_get_contents($this->dir . "/" . $contact['path']);
        $dom = $this->parser->getDomDocument($html);
        $xpath = new DOMXPath($dom);

        $items = $xpath->query('//div[contains(@class, "item")]');

        foreach ($items as $item) {

            if ($item->getAttribute('class') !== "item") {
                continue;
            }

            $messageId = null;

            if ($message = $xpath->query('.//div[contains(@class, "message")]', $item)) {
                $messageId = (int) $message->item(0)->getAttribute('data-id');
            }

            if (!empty($contact['write'])) {

                if ($messageHeader = $xpath->query('.//div[contains(@class, "message__header")]', $item)) {
                    if ($createdAt = $messageHeader->item(0)->textContent ?? null) {
                        $name = trim(explode(",", $createdAt)[0] ?? "");
                        $createdAt = trim(explode(",", $createdAt)[1] ?? "");
                    }
                }

                if (!empty($createdAt)) {
                    $createdAt = $this->getDate($createdAt);
                }

                if ($content = $xpath->query('.//div[contains(@class, "message")]', $item)) {
                    $contents = $xpath->query('.//div', $content->item(0));
                    foreach ($contents as $itemContent) {
                        if ($itemContent->getAttribute('class') == "") {
                            $message = trim($itemContent->textContent ?? "");
                        }
                    }
                }

                $messages[] = [
                    'chat_id' => $contact['chatId'],
                    'message_id' => $messageId ?? null,
                    'from_name' => $name ?? null,
                    'message' => $message ?? null,
                    'created_at' => $createdAt ?? null,
                ];
            }

            $this->count++;
        }

        $this->parser->write(".");

        if (!empty($contact['write'])) {

            collect($messages ?? [])
                ->sortBy('created_at')
                ->each(fn ($data) => $this->createMessage($data));

            return;
        }

        if ($pagination = $xpath->query('//div[contains(@class, "pagination")]')) {
            foreach ($pagination as $page) {
                $links = $xpath->query('.//a', $page);
                foreach ($links as $link) {
                    if (is_numeric($link->textContent ?? null)) {
                        $pages[] = [
                            'page' => (int) $link->textContent,
                            'href' => $contact['chatId'] . "/" . $link->getAttribute('href'),
                        ];
                    }
                }
            }
        }

        $this->pages = collect($this->pages)
            ->merge($pages ?? [])
            ->unique('page')
            ->sortBy('page')
            ->values();

        $this->page++;

        if ($currentPage = $this->pages->firstWhere('page', $this->page)) {
            $contact['path'] = $currentPage['href'];
            $this->getMessages($contact);
        }
    }

    /**
     * Создание и обновления сообщения
     * 
     * @param array $data
     * @return \Kolgaev\Parser\Models\VkMessage
     */
    public function createMessage($data)
    {
        $message = Message::firstOrNew([
            'archive' => "vk",
            'chat_id' => $data['chat_id'] ?? null,
            'message_id' => $data['message_id'] ?? null,
        ]);

        $message->fill($data);

        if ($message->id) {
            $this->countMessageUpdated++;
        } else {
            $this->countMessageCreated++;
        }

        $message->save();

        return $message;
    }

    /**
     * Парсинг даты
     * 
     * @param string $string
     * @return \Carbon\Carbon|null
     */
    public function getDate($string)
    {
        $date = null;

        $string = Str::lower($string);
        $string = Str::replace(["в ", "at ", "on ", "(edited)"], "", $string);
        $array = explode("(", $string);
        $string = trim($array[0] ?? $string);

        $months = [
            " янв ",
            " фев ",
            " мар ",
            " апр ",
            " мая ",
            " июн ",
            " июл ",
            " авг ",
            " сен ",
            " окт ",
            " ноя ",
            " дек ",
        ];
        $replace = [
            "-1-",
            "-2-",
            "-3-",
            "-4-",
            "-5-",
            "-6-",
            "-7-",
            "-8-",
            "-9-",
            "-10-",
            "-11-",
            "-12-"
        ];

        $string = Str::replace($months, $replace, $string);

        try {
            $date = Carbon::createFromFormat("h:i:s A d M Y", $string);
        } catch (Exception $e) {
        }

        if (!$date) {
            try {
                $date = Carbon::createFromFormat("d-m-Y H:i:s", $string);
            } catch (Exception $e) {
            }
        }

        return $date;
    }
}
