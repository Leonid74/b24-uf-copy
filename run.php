<?php /** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

/**
 * CLI точка входа мини-приложения.
 *
 * Использование:
 *   php run.php                     - обычный запуск (продолжит с курсора, если файл состояния есть)
 *   php run.php --resume            - явное возобновление с последнего курсора
 *   php run.php --restart           - полный сброс состояния и обход с нуля
 *   php run.php --dry-run           - прогон без фактической записи
 *   php run.php --help              - справка
 *
 * PHP version 8.2+
 *
 * @version   1.2
 *
 * @author    Leonid Sheikman <Leonid74>
 * @copyright 2026 Leonid Sheikman
 * @license   The 3-Clause BSD License (https://opensource.org/license/bsd-3-clause)
 */

use Leonid74\B24UfCopy\Bitrix24Client;
use Leonid74\B24UfCopy\DealProcessor;
use Leonid74\B24UfCopy\Logger;
use Leonid74\B24UfCopy\ReportGenerator;
use Leonid74\B24UfCopy\StateStorage;

require_once __DIR__ . '/vendor/autoload.php';

// Парсинг CLI-аргументов
$options = parseArgs($argv);

if ($options['help']) {
    printHelp();
    exit(0);
}

// Загрузка конфига
$config = require __DIR__ . '/config.php';

if (!is_array($config) || empty($config['webhook_url']) || str_contains($config['webhook_url'], 'XXXXXX')) {
    fwrite(STDERR, "Ошибка: укажите корректный webhook_url в config.php\n");
    exit(1);
}

// Инициализация компонентов
$logger = new Logger($config['log_dir']);
$state  = new StateStorage($config['state_file']);

// Обработка флагов --restart / --resume
if ($options['restart']) {
    $logger->info('Флаг --restart: сбрасываю состояние и начинаю с нуля');

    try {
        $state->reset();
    } catch (Throwable $e) {
        fwrite(STDERR, 'Ошибка сброса состояния: ' . $e->getMessage() . "\n");
        exit(1);
    }
} elseif ($state->isFinished() && !$options['resume']) {
    $logger->warning('Предыдущая обработка уже завершена. Для повторного прогона используйте --restart');
    exit(0);
} elseif ($state->getLastProcessedId() > 0) {
    $logger->info('Найдено сохранённое состояние, возобновляю обработку', [
        'last_processed_id' => $state->getLastProcessedId(),
        'stats'             => $state->getStats(),
    ]);
}

$client = new Bitrix24Client(
    webhookUrl: $config['webhook_url'],
    logger: $logger,
    throttleUs: $config['throttle_us'],
    maxRetries: $config['max_retries'],
    retryBaseDelay: $config['retry_base_delay'],
    operatingThreshold: $config['operating_threshold'],
    operatingCooldown: $config['operating_cooldown'],
    sslVerify: $config['ssl_verify'] ?? true,
);

$processor = new DealProcessor(
    client: $client,
    state: $state,
    logger: $logger,
    sourceField: $config['source_field'],
    targetField: $config['target_field'],
    batchSize: $config['batch_size'],
    dryRun: $options['dry_run'],
);

// Обработка сигналов для graceful shutdown
if (function_exists('pcntl_signal') && function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    $handler = static function (int $signal) use ($processor, $logger): void {
        $logger->warning("Получен сигнал $signal, инициирую graceful shutdown");
        $processor->requestStop();
    };
    pcntl_signal(SIGINT, $handler);
    pcntl_signal(SIGTERM, $handler);
} else {
    $logger->warning('Расширение pcntl недоступно, graceful shutdown по Ctrl+C не активирован');
}

// Запуск обработки
$completed = false;

try {
    $completed = $processor->run();
} catch (Throwable $e) {
    $logger->error('Фатальная ошибка обработки: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);

    try {
        $state->save();
    } catch (Throwable $saveError) {
        $logger->error('Не удалось сохранить состояние: ' . $saveError->getMessage());
    }
}

// Итоговая статистика
$stats = $state->getStats();
$logger->info('Итоговая статистика', $stats);

// Генерация HTML-отчёта
try {
    (new ReportGenerator())->generate(
        outputPath: $config['report_file'],
        stateData: $state->snapshot(),
        sourceField: $config['source_field'],
        targetField: $config['target_field'],
        dryRun: $options['dry_run'],
        completed: $completed,
    );
    $logger->info('HTML-отчёт сохранён', ['path' => $config['report_file']]);
} catch (Throwable $e) {
    $logger->error('Не удалось сгенерировать HTML-отчёт: ' . $e->getMessage());
}

exit($completed ? 0 : 2);

/**
 * Распарсить аргументы командной строки.
 *
 * @param list<string> $argv
 *
 * @return array{help: bool, dry_run: bool, resume: bool, restart: bool}
 */
function parseArgs(array $argv): array
{
    $options = [
        'help'    => false,
        'dry_run' => false,
        'resume'  => false,
        'restart' => false,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        switch ($arg) {
            case '--help':
            case '-h':
                $options['help'] = true;

                break;
            case '--dry-run':
                $options['dry_run'] = true;

                break;
            case '--resume':
                $options['resume'] = true;

                break;
            case '--restart':
                $options['restart'] = true;

                break;
            default:
                fwrite(STDERR, "Неизвестный аргумент: $arg\n");
                exit(1);
        }
    }

    if ($options['resume'] && $options['restart']) {
        fwrite(STDERR, "Флаги --resume и --restart взаимоисключающие\n");
        exit(1);
    }

    return $options;
}

/**
 * Вывести справку по использованию.
 */
function printHelp(): void
{
    echo <<<HELP
        Мини-приложение для копирования значений UF-полей в сделках Bitrix24.

        Использование:
          php run.php [опции]

        Опции:
          --resume      Явно возобновить обработку с последнего сохранённого ID
          --restart     Сбросить состояние и начать обход с нуля
          --dry-run     Прогон без фактической записи (только подсчёт)
          -h, --help    Показать эту справку

        Файл конфигурации: config.php
        Файл состояния:    state/progress.json
        Логи:              logs/run-YYYY-MM-DD.log
        HTML-отчёт:        logs/report.html

        HELP;
}
