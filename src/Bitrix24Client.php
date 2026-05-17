<?php

declare(strict_types=1);

/**
 * Низкоуровневый клиент REST API Bitrix24 для работы через входящий вебхук.
 *
 * Возможности:
 * - Одиночные вызовы методов (call).
 * - Пакетные вызовы (batch) - до 50 команд за один HTTP-запрос.
 * - Соблюдение лимитов: leaky bucket (~2 rps) + контроль time.operating.
 * - Экспоненциальный backoff при сетевых сбоях и HTTP 503.
 *
 * PHP version 8.2+
 *
 * @version   1.0
 *
 * @author    Leonid Sheikman <Leonid74>
 * @copyright 2026 Leonid Sheikman
 * @license   The 3-Clause BSD License (https://opensource.org/license/bsd-3-clause)
 */

namespace B24UfCopy;

use InvalidArgumentException;
use RuntimeException;

final class Bitrix24Client implements Bitrix24ClientInterface
{
    /**
     * URL входящего вебхука (с замыкающим слешем).
     */
    private string $webhookUrl;

    /**
     * Пауза между HTTP-вызовами в микросекундах.
     */
    private int $throttleUs;

    /**
     * Максимальное число повторов при ошибках.
     */
    private int $maxRetries;

    /**
     * Базовая задержка повтора в секундах (для экспоненциального backoff).
     */
    private int $retryBaseDelay;

    /**
     * Порог time.operating, при превышении которого включается принудительная пауза.
     */
    private float $operatingThreshold;

    /**
     * Длительность принудительной паузы при превышении operating-порога (сек).
     */
    private int $operatingCooldown;

    private Logger $logger;

    /**
     * @param string $webhookUrl         URL вебхука
     * @param Logger $logger             Логгер
     * @param int    $throttleUs         Пауза между запросами (мкс)
     * @param int    $maxRetries         Максимум повторов
     * @param int    $retryBaseDelay     База backoff (сек)
     * @param float  $operatingThreshold Порог time.operating (сек)
     * @param int    $operatingCooldown  Cooldown при превышении (сек)
     */
    public function __construct(
        string $webhookUrl,
        Logger $logger,
        int $throttleUs = 500_000,
        int $maxRetries = 3,
        int $retryBaseDelay = 1,
        float $operatingThreshold = 450.0,
        int $operatingCooldown = 30
    ) {
        $this->webhookUrl         = rtrim($webhookUrl, '/') . '/';
        $this->logger             = $logger;
        $this->throttleUs         = $throttleUs;
        $this->maxRetries         = $maxRetries;
        $this->retryBaseDelay     = $retryBaseDelay;
        $this->operatingThreshold = $operatingThreshold;
        $this->operatingCooldown  = $operatingCooldown;
    }

    /**
     * Одиночный вызов метода Bitrix24.
     *
     * @param string               $method Имя метода (например crm.deal.list)
     * @param array<string, mixed> $params Параметры
     *
     * @return array<string, mixed> Полный декодированный ответ Bitrix24
     *
     * @throws RuntimeException При исчерпании повторов или фатальной ошибке API
     */
    public function call(string $method, array $params = []): array
    {
        return $this->request($method, $params);
    }

    /**
     * Пакетный вызов нескольких методов одним HTTP-запросом.
     *
     * Bitrix24 ограничивает batch 50 командами. Если передано больше - будет исключение.
     *
     * @param array<string, array{method: string, params: array<string, mixed>}> $commands Карта commandId => [method, params]
     * @param bool                                                               $halt     Прерывать ли пакет при первой ошибке (по умолчанию нет)
     *
     * @return array{result: array<string, mixed>, result_error: array<string, mixed>, result_total: array<string, mixed>}
     *
     * @throws InvalidArgumentException Если команд больше 50
     */
    public function batch(array $commands, bool $halt = false): array
    {
        if (count($commands) > 50) {
            throw new InvalidArgumentException('Batch не может содержать более 50 команд.');
        }
        if ($commands === []) {
            return ['result' => [], 'result_error' => [], 'result_total' => []];
        }

        $cmd = [];
        foreach ($commands as $id => $c) {
            // Bitrix24 ожидает строку вида "method?param1=value&param2[key]=value"
            $cmd[$id] = $c['method'] . '?' . http_build_query($c['params']);
        }

        $response = $this->request('batch', [
            'halt' => $halt ? 1 : 0,
            'cmd'  => $cmd,
        ]);

        // Bitrix24 возвращает структуру вида { result: { result: {...}, result_error: {...}, result_total: {...} } }
        $raw = $response['result'];
        if (!is_array($raw)) {
            return ['result' => [], 'result_error' => [], 'result_total' => []];
        }

        $innerResult      = $raw['result'] ?? [];
        $innerResultError = $raw['result_error'] ?? [];
        $innerResultTotal = $raw['result_total'] ?? [];

        return [
            'result'       => is_array($innerResult)      ? $innerResult      : [],
            'result_error' => is_array($innerResultError) ? $innerResultError : [],
            'result_total' => is_array($innerResultTotal) ? $innerResultTotal : [],
        ];
    }

    /**
     * Низкоуровневое выполнение HTTP-запроса с повторами и троттлингом.
     *
     * @param string               $method Имя метода API
     * @param array<string, mixed> $params Параметры
     *
     * @return array<string, mixed> Декодированный ответ
     *
     * @throws RuntimeException При исчерпании повторов
     */
    private function request(string $method, array $params): array
    {
        $url = $this->webhookUrl . $method . '.json';

        $attempt   = 0;
        $lastError = '';

        while ($attempt <= $this->maxRetries) {
            $ch = curl_init($url);
            if ($ch === false) {
                throw new RuntimeException('Не удалось инициализировать cURL.');
            }

            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query($params),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER         => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            ]);

            $raw        = curl_exec($ch);
            $httpCode   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $curlError  = curl_error($ch);
            curl_close($ch);

            // Дроссель - пауза после любого запроса (даже неудачного)
            usleep($this->throttleUs);

            if ($raw === false) {
                $lastError = "cURL error: $curlError";
                $this->logger->warning("Сетевая ошибка при вызове $method", [
                    'attempt' => $attempt + 1,
                    'error'   => $curlError,
                ]);
                $this->sleepBackoff($attempt);
                $attempt++;
                continue;
            }

            $headersRaw = substr((string) $raw, 0, $headerSize);
            $body       = substr((string) $raw, $headerSize);

            // 503 - превышение лимита, читаем Retry-After и ждём
            if ($httpCode === 503) {
                $retryAfter = $this->extractRetryAfter($headersRaw);
                $wait       = $retryAfter ?? $this->backoffSeconds($attempt);
                $this->logger->warning("HTTP 503 от Bitrix24 при вызове $method, жду $wait сек", [
                    'attempt'     => $attempt + 1,
                    'retry_after' => $retryAfter,
                ]);
                sleep($wait);
                $attempt++;
                continue;
            }

            $decoded = json_decode($body, true);
            if (!is_array($decoded)) {
                $lastError = "Невалидный JSON в ответе (HTTP $httpCode)";
                $this->logger->warning("Ошибка декодирования ответа $method", [
                    'http_code'    => $httpCode,
                    'body_preview' => substr($body, 0, 200),
                ]);
                $this->sleepBackoff($attempt);
                $attempt++;
                continue;
            }

            // Фатальные ошибки уровня API (не уровня отдельных batch-команд)
            if (isset($decoded['error'])) {
                $errorCode = (string) $decoded['error'];
                $errorDesc = (string) ($decoded['error_description'] ?? '');

                // Транзиентные ошибки, т.е. временные, повторяем
                if (in_array($errorCode, ['QUERY_LIMIT_EXCEEDED', 'OVERLOAD_LIMIT'], true)) {
                    $this->logger->warning("Транзиентная ошибка API $errorCode при $method, повторяем", [
                        'attempt'     => $attempt + 1,
                        'description' => $errorDesc,
                    ]);
                    $this->sleepBackoff($attempt);
                    $attempt++;
                    continue;
                }

                throw new RuntimeException(
                    "API Bitrix24 вернул ошибку при вызове $method: $errorCode - $errorDesc"
                );
            }

            // Контроль time.operating - если приближаемся к лимиту, делаем паузу
            $time = $decoded['time'] ?? [];
            if (is_array($time) && isset($time['operating']) && (float) $time['operating'] >= $this->operatingThreshold) {
                $this->logger->warning('Приближение к лимиту time.operating, пауза', [
                    'operating'    => $time['operating'],
                    'cooldown_sec' => $this->operatingCooldown,
                ]);
                sleep($this->operatingCooldown);
            }

            return $decoded;
        }

        throw new RuntimeException("Исчерпано число повторов для $method. Последняя ошибка: $lastError");
    }

    /**
     * Извлечь заголовок Retry-After из сырых HTTP-заголовков.
     *
     * @param string $headersRaw Сырые заголовки ответа
     *
     * @return int|null Секунды ожидания или null, если заголовок отсутствует
     */
    private function extractRetryAfter(string $headersRaw): ?int
    {
        foreach (preg_split('/\r?\n/', $headersRaw) ?: [] as $line) {
            if (stripos($line, 'Retry-After:') === 0) {
                $value = trim(substr($line, strlen('Retry-After:')));
                if (ctype_digit($value)) {
                    return (int) $value;
                }
            }
        }

        return null;
    }

    /**
     * Длительность паузы для экспоненциального backoff.
     *
     * @param int $attempt Номер попытки (от 0)
     */
    private function backoffSeconds(int $attempt): int
    {
        return $this->retryBaseDelay * (2 ** $attempt);
    }

    /**
     * Сделать паузу с экспоненциальным backoff.
     *
     * @param int $attempt Номер попытки (от 0)
     */
    private function sleepBackoff(int $attempt): void
    {
        sleep($this->backoffSeconds($attempt));
    }
}
