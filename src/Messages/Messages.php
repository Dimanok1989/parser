<?php

namespace Kolgaev\VkParser\Messages;

use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Exception;
use Kolgaev\VkParser\Models\VkMessage;
use Kolgaev\VkParser\Parser;

class Messages extends Parser
{
    /**
     * Index messages file path
     * 
     * @var string
     */
    protected $baseDir = __DIR__ . "/../../archive/messages";

    /**
     * Список найденных контактов
     * 
     * @var \Illuminate\Support\Collection
     */
    protected $contacts;

    /**
     * Текущая страница обработки сообщений
     * 
     * @var int
     */
    protected $page = 1;

    /**
     * Страницы сообщений
     * 
     * @var \Illuminate\Support\Collection
     */
    protected $pages;

    /**
     * Количество сообщений
     * 
     * @var int
     */
    protected $count = 0;

    /**
     * Инициализация объекта
     * 
     * @return void
     */
    public function __construct()
    {
        if (!file_exists($this->baseDir . "/index-messages.html")) {
            throw new Exception("File index-messages.html not found");
        }

        (new Migration)->up();

        parent::__construct();
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
     * Main messages page
     * 
     * @return void
     */
    public function index()
    {
        $indexHtml = file_get_contents($this->baseDir . "/index-messages.html");
        $dom = $this->getDomDocument($indexHtml);
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

        $this->writePage();
    }

    /**
     * Contact selects page
     * 
     * @return void
     */
    public function writePage()
    {
        $item = null;

        foreach ($this->contacts as $key => $contact) {
            $this->line("<fg=green>$key</> {$contact['name']}");
        }

        while (!$item) {

            $this->line();
            $this->output->write("Выберите идентификатор пользователя: ");

            $line_host = trim(fgets(STDIN));

            $item = (int) $line_host;
        }

        if (empty($this->contacts[$item])) {
            throw new Exception("Not fount item [$item] contacts collection");
        }

        $contact = $this->contacts[$item];

        $this->line();
        $this->line("<fg=blue;options=bold>{$contact['name']}</>");
        $this->line($contact['chatId']);
        $this->line();

        $this->getMessages($contact);

        $this->line("\n");
        $this->line("Найдено сообщений: " . $this->count);

        $write = false;

        while (!$write) {

            $this->output->write("Сохранить их в базу данных? (y/Y/n/N) [Y] ");

            $line_update = trim(fgets(STDIN));

            if (empty($line_update)) {
                $line_update = "Y";
            }

            $write = $line_update;
        }

        if (in_array($line_update, ['y', 'Y', 'д', 'Д'])) {
            $contact['write'] = true;
            $write = true;
        } else {
            $write = false;
        }

        if (!$write) {
            $this->line("<fg=yellow>Остановлено!</>");
        } else {

            $this->info("\nЗапись в базу данных:");

            $contact['path'] = $this->pages->firstWhere('page', 1)['href']
                ?? $contact['chatId'] . "/messages0.html";

            $this->getMessages($contact);
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

        $html = file_get_contents($this->baseDir . "/" . $contact['path']);
        $dom = $this->getDomDocument($html);
        $xpath = new DOMXPath($dom);

        $items = $xpath->query('//div[contains(@class, "item")]');

        foreach ($items as $item) {

            $messageId = null;

            if ($message = $xpath->query('.//div[contains(@class, "message")]', $item)) {
                $messageId = (int) $message->item(0)->getAttribute('data-id');
            }

            if ($messageHeader = $xpath->query('.//div[contains(@class, "message__header")]', $item)) {
                if ($createdAt = $messageHeader->item(0)->textContent ?? null) {
                    $name = trim(explode(",", $createdAt)[0] ?? "");
                    $createdAt = trim(explode(",", $createdAt)[1] ?? "");
                }
            }

            if (!empty($createdAt)) {
                $createdAt = str_replace(" в ", " ", $createdAt);
                $date = explode(" ", $createdAt);
                $createdAt = $date[2] . "-"
                    . match ($date[1]) {
                        "янв" => 1,
                        "фев" => 2,
                        "мар" => 3,
                        "апр" => 4,
                        "мая" => 5,
                        "июн" => 6,
                        "июл" => 7,
                        "авг" => 8,
                        "сен" => 9,
                        "окт" => 10,
                        "ноя" => 11,
                        "дек" => 12,
                    }
                    . "-" . $date[0]
                    . " " . $date[3];
            }

            if ($content = $xpath->query('.//div[contains(@class, "message")]', $item)) {
                $contents = $xpath->query('.//div', $content->item(0));
                foreach ($contents as $itemContent) {
                    if ($itemContent->getAttribute('class') == "") {
                        $message = trim($itemContent->textContent ?? "");
                    }
                }
            }

            if (!empty($contact['write'])) {
                $messages[] = [
                    'chat_id' => $contact['chatId'],
                    'message_id' => $messageId ?? null,
                    'user_name' => $name ?? null,
                    'message' => $message ?? null,
                    'created_at' => $createdAt ? Carbon::parse($createdAt) : null,
                ];
            }

            $this->count++;
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

        if (!empty($contact['write'])) {

            $messages = collect($messages ?? [])
                ->sortBy('created_at')
                ->each(fn ($data) => $this->createMessage($data));

            $this->page--;
        } else {
            $this->page++;
        }

        $this->output->write(".");

        if ($currentPage = $this->pages->firstWhere('page', $this->page)) {
            $contact['path'] = $currentPage['href'];
            $this->getMessages($contact);
        }
    }

    /**
     * Создание и обновления сообщения
     * 
     * @param array $data
     * @return \Kolgaev\VkParser\Models\VkMessage
     */
    public function createMessage($data)
    {
        $message = VkMessage::firstOrNew([
            'chat_id' => $data['chat_id'] ?? null,
            'message_id' => $data['message_id'] ?? null,
        ]);

        $message->fill($data);
        $message->save();

        return $message->refresh();
    }
}
