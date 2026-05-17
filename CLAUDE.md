# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Что это

PHP 8.2+ скрипт для массового копирования значения одного пользовательского поля сделки (UF-поля) Bitrix24 в другое через входящий вебхук. Проект без Composer — автозагрузчик классов реализован вручную через `spl_autoload_register` в `run.php`.

## Запуск

```bash
php run.php              # обычный запуск (продолжает с курсора, если есть файл состояния)
php run.php --dry-run    # прогон без записи в Bitrix24
php run.php --resume     # явное возобновление с курсора
php run.php --restart    # сброс состояния и обход с нуля
```

Перед запуском — заполнить `config.php`: `webhook_url`, `source_field`, `target_field`.

## Установка зависимостей

```bash
composer install
```

## Линтер

```bash
# проверить стиль
vendor/bin/php-cs-fixer fix --dry-run --diff

# исправить автоматически
vendor/bin/php-cs-fixer fix
```

Используется `@PhpCsFixer` ruleset — **не** PSR-12. Конфигурация полностью описана в `.php-cs-fixer.php`. Кеш линтера: `.php-cs-fixer.cache` (в `.gitignore`).

## Статический анализ

```bash
vendor/bin/phpstan analyse
```

Уровень 8. Анализируются `src/`, `run.php` и `tests/`. Конфиг: `phpstan.neon`.

## Тесты

```bash
# весь набор
vendor/bin/phpunit

# конкретный класс
vendor/bin/phpunit tests/StateStorageTest.php

# конкретный тест
vendor/bin/phpunit --filter testSaveAndReloadPreservesState
```

Тест-классы: `StateStorageTest`, `DealProcessorTest`, `ReportGeneratorTest` в `tests/`. Зависимости (`Bitrix24ClientInterface`) мокируются через PHPUnit. Реальные файловые операции выполняются во временных директориях (`sys_get_temp_dir()`), которые очищаются в `tearDown()`.

## Архитектура

Точка входа — `run.php`. Он разбирает CLI-аргументы, загружает `config.php`, инициализирует компоненты и регистрирует обработчики `SIGINT`/`SIGTERM` через `pcntl`.

Компоненты в `src/` (namespace `B24UfCopy\`):

| Класс | Роль |
|---|---|
| `Bitrix24Client` | HTTP-клиент с троттлингом, batch-запросами, backoff |
| `Bitrix24ClientInterface` | Интерфейс клиента (для подмены в тестах) |
| `DealProcessor` | Основной цикл: выборка → фильтрация → batch-обновление |
| `StateStorage` | Атомарное хранилище состояния в JSON |
| `Logger` | Вывод в stdout + суточная ротация лог-файлов |
| `ReportGenerator` | Генерация HTML-отчёта |

### Алгоритм обхода

`DealProcessor` итерирует сделки по ID-курсору: запрос `crm.deal.list` с фильтром `>ID` и сортировкой `ID ASC`, `start: -1` (отключает подсчёт total — быстрее на больших порталах). После каждой пачки (до 50 сделок) курсор сдвигается на максимальный ID, состояние атомарно сохраняется.

### Фильтрация кандидатов на запись

Обновление выполняется **только** если:
- исходное поле (`source_field`) непустое — проверка через `empty()`, значит `'0'` считается пустым;
- целевое поле (`target_field`) пустое (`empty()`).

### Rate limiting в `Bitrix24Client`

- Дроссель: пауза `throttle_us` мкс после каждого HTTP-запроса (по умолчанию 500 000 мкс = 0.5 с ≈ 2 rps).
- HTTP 503: читает `Retry-After`, ждёт и повторяет.
- `time.operating ≥ operating_threshold`: принудительная пауза `operating_cooldown` сек.
- Транзиентные ошибки API (`QUERY_LIMIT_EXCEEDED`, `OVERLOAD_LIMIT`): экспоненциальный backoff.

### Атомарная запись состояния

`StateStorage::save()` пишет во временный файл (`/state/.progress.<random>.tmp`), затем делает `rename()` — POSIX-атомарная операция. Повреждённый `progress.json` при загрузке переименовывается в `.corrupted.<timestamp>` и обработка начинается заново.

## Ключевые ограничения

- Batch-запрос Bitrix24 принимает максимум **50 команд**. `DealProcessor` обрезает пачку до `min($batchSize, 50)`.
- Batch-обновление вызывается с `halt: 0` — ошибки отдельных команд не прерывают пакет.
- Расширение `pcntl` опционально: graceful shutdown по Ctrl+C активируется только если оно доступно.
