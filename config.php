<?php

declare(strict_types=1);

/**
 * Конфигурация мини-приложения для копирования значений UF-полей в сделках Bitrix24.
 *
 * PHP version 8.2+
 *
 * @version   1.1
 *
 * @author    Leonid Sheikman <Leonid74>
 * @copyright 2026 Leonid Sheikman
 * @license   The 3-Clause BSD License (https://opensource.org/license/bsd-3-clause)
 */

return [
    // URL входящего вебхука Bitrix24.
    // Пример: https://portal.bitrix24.ru/rest/1/abcdef1234567890/
    'webhook_url' => 'https://your-portal.bitrix24.ru/rest/1/XXXXXXXXXXXXXXXX/',

    // Имя исходного пользовательского поля (откуда копируем).
    'source_field' => 'UF_CRM_SOURCE',

    // Имя целевого пользовательского поля (куда копируем).
    'target_field' => 'UF_CRM_TARGET',

    // Размер пачки для crm.deal.list и batch.call. Максимум - 50.
    'batch_size' => 50,

    // Пауза между HTTP-вызовами (микросекунды). 500 000 = 0.5 сек = 2 rps.
    // Соответствует устойчивой пропускной способности leaky bucket Bitrix24.
    'throttle_us' => 500_000,

    // Максимальное количество повторов при ошибках 503/сетевых сбоях.
    'max_retries' => 3,

    // Базовая задержка для экспоненциального backoff (секунды).
    'retry_base_delay' => 1,

    // Безопасный порог по time.operating (Bitrix24 ограничивает 480 сек на окно 600 сек).
    // При превышении делаем принудительную паузу.
    'operating_threshold' => 450.0,

    // Пауза при превышении operating_threshold (секунды).
    'operating_cooldown' => 30,

    // Путь к файлу состояния (относительно корня проекта).
    'state_file' => __DIR__ . '/state/progress.json',

    // Каталог логов.
    'log_dir' => __DIR__ . '/logs',

    // Путь к HTML-отчёту.
    'report_file' => __DIR__ . '/logs/report.html',

    // Проверять SSL-сертификат сервера Bitrix24.
    // Установите false, если запускаете локально и получаете ошибку:
    // "SSL certificate problem: self-signed certificate in certificate chain".
    // Не отключайте в production-среде без необходимости.
    'ssl_verify' => true,
];
