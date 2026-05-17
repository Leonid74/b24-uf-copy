# B24 UF Copy

Мини-приложение на PHP 8.2+ для копирования значения одного пользовательского поля сделки Bitrix24 в другое. Обходит все сделки текущего портала через входящий вебхук, использует пакетные запросы и поддерживает возобновление с места остановки.

## Возможности

- Обход всех сделок портала с пагинацией по ID (быстрая, не зависит от `start`).
- Batch-обновление до 50 сделок за один HTTP-запрос (`halt: 0` - ошибки одиночных команд не валят пачку).
- Соблюдение лимитов Bitrix24:
  - Лимит запросов ~2 rps (пауза 0.5 сек между запросами).
  - Контроль `time.operating` с принудительной паузой при приближении к 480/600.
  - Обработка HTTP 503 + `Retry-After`.
  - Экспоненциальный backoff при сетевых сбоях.
- Идемпотентность: целевое поле перезаписывается **только если оно пустое**.
- Исходное поле проверяется через `empty()`: `''`, `'0'`, `null`, `0` считаются пустыми.
- Возобновление с курсора последнего обработанного ID.
- Graceful shutdown по `SIGINT`/`SIGTERM` (Ctrl+C) - успевает дописать состояние.
- HTML-отчёт по итогам.

## Установка

1. Клонируйте репозиторий:
   ```bash
   git clone https://github.com/Leonid74/b24-uf-copy.git
   cd b24-uf-copy
   ```
   или скачайте ZIP-архив вручную со [страницы репозитория](https://github.com/Leonid74/b24-uf-copy) и распакуйте его.
2. Установите зависимости:
   ```bash
   composer install
   ```
3. Создайте входящий вебхук в Bitrix24 с правом `crm` и скопируйте его URL.
4. Откройте `config.php` и заполните:
   - `webhook_url`  - URL вебхука.
   - `source_field` - имя исходного UF-поля (например `UF_CRM_1234567890`).
   - `target_field` - имя целевого UF-поля.

## Запуск

```bash
# Обычный запуск (автоматически продолжит с курсора, если файл состояния есть)
php run.php

# Тестовый прогон без фактической записи (для оценки объёма работы)
php run.php --dry-run

# Явное возобновление
php run.php --resume

# Полный сброс и обход с нуля
php run.php --restart

# Справка
php run.php --help
```

## Возобновление после сбоя

Состояние пишется в `state/progress.json` после **каждой** обработанной пачки (50 сделок). Запись атомарна (tempfile + rename), повредить файл состояния невозможно.

При повторном запуске скрипт автоматически продолжит с `last_processed_id`. При желании сбросить - `--restart`.

## Структура проекта

```
b24-uf-copy/
├── composer.json
├── config.php
├── run.php
├── phpstan.neon
├── phpunit.xml
├── src/
│   ├── Bitrix24Client.php   - HTTP-клиент с batch и троттлингом
│   ├── DealProcessor.php    - основной цикл обработки
│   ├── StateStorage.php     - состояние (атомарная запись JSON)
│   ├── ReportGenerator.php  - HTML-отчёт
│   └── Logger.php           - логгер
├── tests/
│   ├── StateStorageTest.php
│   ├── DealProcessorTest.php
│   └── ReportGeneratorTest.php
├── state/
│   └── progress.json        - состояние (создаётся автоматически)
└── logs/
    ├── run-YYYY-MM-DD.log   - текстовый лог
    └── report.html          - HTML-отчёт
```

## Требования

- PHP 8.2+
- Composer 2+
- Расширения: `curl`, `json`, `pcntl` (опционально, для Ctrl+C)

## Copyrights

- @author    Leonid Sheikman <Leonid74>
- @copyright 2026 Leonid Sheikman
- @license   The 3-Clause BSD License (https://opensource.org/license/bsd-3-clause)
