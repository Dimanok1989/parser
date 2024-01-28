## Установка

#### GitHub

```
git clone https://github.com/Dimanok1989/parser.git
cd parser
composer install
```

#### Packagist

Для установки как отдельный проект
```
composer create-project kolgaev/parser parser
```
или для установки пакета в собственный проект
```
composer require kolgaev/parser
```

## Настройка

#### Конфиг

Создать файл `.env` и заполнить его по аналогии с `.env.example`

Поддерживаются следующие драйвера базы данных:
- `mysql`
- `pgsql`

Можно указать полный путь до каталога с архивами
`ARCHIVE_VK=`
`ARCHIVE_TELEGRAM=`

#### Распаковка архива

Распаковать содержимое архива Телеграм в каталог `archive-telegram` (архив в формте html)
```
archive-telegram
├── chats
|   ├── chat_01
|   |   ├── photos
|   |   |   ├── ...
|   |   |   ├── photo_1@01-06-2023_15-21-40.jpg
|   |   |   └── photo_1@01-06-2023_15-21-40_thumb.jpg
|   |   ├── ...
|   |   ├── messages.html
|   |   ├── messages2.html
|   |   └── messages3.html
|   └── chat_02
|       └── messages.html
├── ...
├── lists
|   ├── chats.html
|   ├── contacts.html
|   ├── frequent.html
|   └── profile_pictures.html
└── export_results.html
```

Распаковать содержимое архива ВК в каталог `archive-vk`
```
archive-vk
├── ads
├── apps
├── audio
├── ...
├── messages
|   ├── 100
|   |   └── messages0.html
|   └── index-messages.html
├── ...
├── favicon.ico
├── index.html
└── style.css

```

## Запуск

Запустить
```
php parse
```

И следовать инструкциям в командной терминале

#### Параметры

- `--telegram` - Начать парсинг из архива телеграм
- `--vk` - Начать парсинг из архива телеграм
- `--chat={number}` - Выбрать чат группу или пользователя
- `-Y` - Сразу начать запись сообщений в базу данных