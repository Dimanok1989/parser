<?php

namespace Kolgaev\Parser\Telegram;

use Carbon\Carbon;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use Exception;
use Illuminate\Support\Str;
use Kolgaev\Parser\Interfaces\ArchivePartInterface;
use Kolgaev\Parser\Interfaces\ParserInterface;
use Kolgaev\Parser\Models\Message;
use Kolgaev\Parser\Models\MessageAttachment;
use Kolgaev\Parser\Parser;
use Kolgaev\Parser\Support\Collection;

class Chats implements ArchivePartInterface, ParserInterface
{
    /**
     * Каталог с данными
     * 
     * @var string
     */
    const DIR = "chats";

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
     * Страницы с данными
     * 
     * @var \Illuminate\Support\Collection
     */
    protected $pages;

    /**
     * Текущая страница к обработке
     * 
     * @var \Illuminate\Support\Collection
     */
    protected $args;

    /**
     * @var \DOMXPath
     */
    protected DOMXPath $xpath;

    /**
     * Инициализации парсера раздела
     * 
     * @param \Kolgaev\Parser\Parser $parser
     * @return void
     */
    public function __construct(protected Parser $parser)
    {
        $this->args = collect($_SERVER['argv'] ?? []);

        $this->dir = env('ARCHIVE_TELEGRAM', $this->parser->dir) . "/" . self::DIR;
        $this->index = $this->parser->dir . "/lists/chats.html";

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

        $items = $xpath->query('//a[contains(@class, "entry block_link clearfix")]');

        foreach ($items as $item) {

            $name = null;
            $messages = null;

            if ($nameDiv = $xpath->query('.//div[contains(@class, "name bold")]', $item)) {
                $name = trim($nameDiv->item(0)->textContent ?? "");
            }

            if ($messagesDiv = $xpath->query('.//div[contains(@class, "details_entry details")]', $item)) {
                $messages = trim($messagesDiv->item(0)->textContent ?? "");
            }

            $path = parse_url($item->getAttribute('href') ?? "", PHP_URL_PATH);
            $pathinfo = pathinfo($path);
            $path = collect(pathinfo($pathinfo['dirname'] ?? "", PATHINFO_BASENAME))
                ->merge($pathinfo['basename'] ?? null)
                ->filter()
                ->join("/");

            $contacts[] = [
                'chatId' => md5($name),
                'name' => $name,
                'messages' => (int) ($messages ?? null),
                'path' => $path,
            ];
        }

        $this->contacts = collect($contacts)->values();

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
                $this->parser->line(
                    "[<fg=green;options=bold>$key</>] {$contact['name']}"
                        . (!empty($contact['messages']) ? " ({$contact['messages']})" : "")
                );
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
        $this->parser->line();

        $this->pages = collect($contact['path']);
        $this->findMessages($contact);

        $this->parser->line("\n");
        $this->parser->line("Найдено сообщений: " . $this->count);
        $this->parser->line();

        $write = $this->args->search("-Y") !== false ? "Y" : false;

        while (!$write) {
            $this->parser->write("Сохранить их в базу данных? (y/Y/n/N) [Y] ");
            $line_update = trim(fgets(STDIN));
            if (empty($line_update)) {
                $line_update = "Y";
            }
            $write = $line_update;
        }

        if (!in_array($write, ['y', 'Y', 'д', 'Д'])) {
            $this->parser->warn("Остановлено!");
        } else {
            $this->parser->info("Запись в базу данных:");
            $this->parser->line();
            $this->pages->each(fn ($path) => $this->getMessages($path, $contact['chatId'] ?? null));
        }
    }

    /**
     * Поиск сообщений
     * 
     * @param array
     * @return void
     */
    public function findMessages($contact)
    {
        $html = file_get_contents($this->dir . "/" . $contact['path']);
        $dom = $this->parser->getDomDocument($html);
        $xpath = new DOMXPath($dom);

        $items = $xpath->query('//div[contains(@class, "message default clearfix")]');
        $this->count += $items->length;
        $this->parser->write(".");

        if ($pages = $xpath->query('//a[contains(@class, "pagination block_link")]')) {

            foreach ($pages as $page) {

                if (trim($page->textContent) != "Next messages") {
                    continue;
                }

                $nextPage = $page->getAttribute('href');
                $path = pathinfo($contact['path'], PATHINFO_DIRNAME) . "/" . $nextPage;

                if ($this->pages->search($path) === false) {

                    $this->pages->push($path);

                    $contact['path'] = $path;
                    $this->findMessages($contact);
                }
            }
        }
    }

    /**
     * Поиск сообщений
     * 
     * @param array $path
     * @param null|string $chatId
     * @return void
     */
    public function getMessages($path, $chatId = null)
    {
        $html = file_get_contents($this->dir . "/" . $path);
        $dom = $this->parser->getDomDocument($html);
        $this->xpath = new DOMXPath($dom);

        $items = $this->xpath->query('//div[contains(@class, "message default clearfix")]');

        $userName = null; // Имя автора сообщения
        $forwardedUserName = null; // Имя автора пересылаемого сообщения

        foreach ($items as $item) {

            $forwardedDate = null; // Дата пересылаемого сообщения

            // Идентификатор сообщения
            $messageId = (int) preg_replace("/[^0-9]/", '', $item->getAttribute('id'));

            // Имя автора сообщения
            $names = $this->getNames($item);

            if ($names->name) {
                $userName = $names->name;
            }

            if ($names->isForwarded && $names->forwardedName) {
                $forwardedUserName = $names->forwardedName;
            } else if (!$names->isForwarded) {
                $forwardedUserName = null;
            }

            if ($names->isForwarded && $names->forwardedDate) {
                $forwardedDate = $names->forwardedDate;
            }

            $messageData = $this->getMessageData(
                $this->xpath->query('.//div[contains(@class, "body")]/div', $item)
            );

            $mesageItem = [
                'chat_id' => $chatId,
                'message_id' => $messageId ?? null,
                'from_name' => $userName ?? null,
                'message' => $messageData->text,
                'created_at' => $messageData->date,
                'forwarded_from_name' => $forwardedUserName ?? null,
                'forwarded_created_at' => $forwardedDate ?? null,
                'is_call' => (bool) $messageData->isCall,
                'is_attach' => $messageData->attachments->isNotEmpty(),
            ];

            $message = Message::firstOrNew([
                'archive' => 'telegram',
                'message_id' => $messageId
            ]);
            $message->fill($mesageItem);
            $message->save();

            foreach ($messageData->attachments as $data) {

                $attach = MessageAttachment::firstOrNew([
                    'message_id' => $message->id,
                    'hash' => $data['hash'] ?? null,
                ]);

                $attach->fill($data);
                $attach->save();
            }
        }

        $this->parser->write(".");
    }

    /**
     * Поиск имени автора сообщения
     * 
     * @param \DOMElement $item
     * @return \Illuminate\Support\Collection
     */
    public function getNames(DOMElement $item)
    {
        $forwarded = $this->xpath
            ->query('.//div[contains(@class, "forwarded body")]/div', $item);

        $fromNames = $this->xpath
            ->query('.//div[contains(@class, "body")]/div[contains(@class, "from_name")]', $item);

        if ($fromNames->length > 1) {

            $name = trim($fromNames->item(0)->textContent);

            $forwardedData = $this->getForwardedData($fromNames->item(1));
            $forwardedName = $forwardedData['name'];
            $forwardedDate = $forwardedData['date'];
        } else if ($fromNames->length == 1) {

            $nameFromForwarded = false;

            if ($forwarded->length) {
                foreach ($forwarded as $f) {
                    if ($f->getAttribute('class') == "from_name") {
                        $forwardedData = $this->getForwardedData($f);
                        $forwardedName = $forwardedData['name'];
                        $forwardedDate = $forwardedData['date'];
                        $nameFromForwarded = true;
                        break;
                    }
                }
            }

            if (!$nameFromForwarded) {
                $name = trim($fromNames->item(0)->textContent);
            }
        }

        return new Collection([
            'name' => $name ?? null,
            'isForwarded' => $forwarded->length > 0,
            'forwardedName' => $forwardedName ?? null,
            'forwardedDate' => $forwardedDate ?? null,
        ]);
    }

    /**
     * Определение имени и даты пересылаемого сообщения
     * 
     * @param \DOMElement $item
     * @return array
     */
    public function getForwardedData(DOMElement $item)
    {
        $name = trim($item->textContent);

        $dateSpan = $item->getElementsByTagName('span');

        if ($dateSpan->length) {

            if (!empty($date = $dateSpan->item(0)->textContent)) {
                $name = trim(Str::replace($date, "", $name));
            }

            if (!empty($title = $dateSpan->item(0)->getAttribute('title'))) {
                $date = Carbon::parse($title)->format("Y-m-d H:i:s");
            }
        }

        return [
            'name' => $name ?? null,
            'date' => $date ?? null,
        ];
    }

    /**
     * Основные данные сообщения
     * 
     * @param \DOMNodeList $body
     * @return \Kolgaev\Parser\Support\Collection
     */
    public function getMessageData(DOMNodeList $body)
    {
        foreach ($body as $item) {

            $class = $item->getAttribute("class");

            // Дата сообщения
            if ($class == "pull_right date details" && empty($data['date'])) {
                $data['date'] = Carbon::parse($item->getAttribute('title'))->format("Y-m-d H:i:s");
            }

            // Текст сообщения
            if ($class == "text" && empty($data['text'])) {
                $data['text'] = trim($item->textContent);
            }

            if ($class == "media_wrap clearfix") {
                $attachments = $this->getAttachments($item);
            }
        }

        $attachments = collect($attachments ?? []);

        $data['isCall'] = !empty($attachments->firstWhere('type', "call"));

        if (empty($data['text']) && $data['isCall']) {
            $data['text'] = $attachments->firstWhere('type', "call")['content'] ?? null;
        }

        if (empty($data['text']) && $attachments->firstWhere('type', "location")) {
            $data['text'] = $attachments->firstWhere('type', "location")['content'] ?? null;
        }

        if (empty($data['text'])) {
            $data['text'] = $attachments->firstWhere('type', "emoji")['content'] ?? null;
        }

        if (empty($data['text'])) {
            $data['text'] = $attachments->firstWhere('type', "file_name")['content'] ?? null;
        }

        $data['attachments'] = $attachments
            ->filter(fn ($item) => !in_array($item['type'] ?? null, ['call', 'location', 'emoji', 'file_name']))
            ->values();

        return new Collection($data ?? []);
    }

    /**
     * Поиск прикрепленных файлов в сообщении
     * 
     * @param \DOMElement $body
     * @return \Illuminate\Support\Collection
     */
    public function getAttachments(DOMElement $body)
    {
        $data = collect([]);

        $items = $this->xpath
            ->query('.//div[contains(@class, "media")]', $body);

        foreach ($items as $item) {

            $class = collect(explode(" ", $item->getAttribute('class')))
                ->map(fn ($i) => trim($i));

            $title = $this->getMediaContent($item, "title");

            // Звонок
            if ($class->search('media_call') !== false) {

                $content = [
                    $title,
                    $this->getMediaContent($item, "details"),
                ];

                $data->push([
                    'type' => "call",
                    'content' => implode(" | ", $content),
                ]);
            }
            // Стикер
            else if ($class->search('media_photo') !== false) {

                if ($title == "Sticker") {

                    $sticker = explode(",", (string) $this->getMediaContent($item, "details"));

                    $data->push([
                        'type' => "emoji",
                        'content' => $sticker[0] ?? null,
                    ]);
                }
            } else {
                $data->push([
                    'type' => "file_name",
                    'content' => $title . " | " . $this->getMediaContent($item, "details"),
                ]);
            }
        }

        $items = $this->xpath
            ->query('.//a', $body);

        foreach ($items as $item) {

            $class = collect(explode(" ", $item->getAttribute('class')))
                ->map(fn ($i) => trim($i));

            if (
                $class->search('photo_wrap') !== false
                || $class->search('animated_wrap') !== false
                || $class->search('video_file_wrap') !== false
                || $class->search('media_file') !== false
                || $class->search('media_audio_file') !== false
                || $class->search('media_voice_message') !== false
                || $class->search('media_photo') !== false
                || $class->search('media_video') !== false
                || $class->search('sticker_wrap') !== false
            ) {

                if ($class->search('photo_wrap') !== false) {
                    $type = "photo";
                } else if ($class->search('photo_wrap') !== false) {
                    $type = "animated";
                } else if ($class->search('video_file_wrap') !== false) {
                    $type = "video";
                } else if ($class->search('media_file') !== false) {
                    $type = "file";
                } else if ($class->search('media_audio_file') !== false) {
                    $type = "audio";
                } else if ($class->search('media_voice_message') !== false) {
                    $type = "voice";
                } else if ($class->search('media_photo') !== false) {
                    $type = "photo";
                } else if ($class->search('media_video') !== false) {
                    $type = "video";
                } else if ($class->search('sticker_wrap') !== false) {
                    $type = "sticker";
                }

                $href =  str_replace("../..", $this->parser::DIR, $item->getAttribute('href'));
                $file = path($href);
                $content = base64_encode(file_get_contents($file));

                $data->push([
                    'hash' => md5_file($file),
                    'type' => $type ?? null,
                    'mime_type' => mime_content_type($file),
                    'path' => $href,
                    'content' => $content,
                ]);
            } else if ($class->search('media_location') !== false) {
                $data->push([
                    'type' => "location",
                    'content' => $this->getMediaContent($item, "details"),
                ]);
            }
        }

        return $data;
    }

    private function getMediaContent($item, $content)
    {
        $callBody = $this->xpath
            ->query('.//div[contains(@class, "body")]/div', $item);

        foreach ($callBody as $call) {
            foreach (explode(" ", $call->getAttribute('class')) as $c) {
                if ($c == $content) {
                    return trim($call->textContent);
                }
            }
        }

        return null;
    }
}
