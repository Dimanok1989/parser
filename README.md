## Установка

```
composer install
```

## Настройка

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
|   └── index-messages.html
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

#### База данных

Поддерживаются следующие драйвера базы данных:
- `mysql`
- `pgsql`

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