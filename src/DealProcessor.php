<?php

declare(strict_types=1);

/**
 * Процессор сделок: основной цикл обхода и копирования значений UF-полей.
 *
 * Алгоритм:
 *  1. Запрос пачки сделок (до 50) с фильтром по ID > курсор, сортировкой ID ASC.
 *  2. Фильтрация в памяти: исходное поле непустое + целевое поле пустое.
 *  3. Batch-обновление выбранных сделок (одним HTTP-запросом).
 *  4. Сдвиг курсора на максимальный ID пачки, сохранение состояния.
 *  5. Повтор до пустой пачки.
 *
 * PHP version 8.2+
 *
 * @version   1.0
 *
 * @author    Leonid Sheikman <Leonid74>
 * @copyright 2026 Leonid Sheikman
 * @license   The 3-Clause BSD License (https://opensource.org/license/bsd-3-clause)
 */

namespace Leonid74\B24UfCopy;

final class DealProcessor
{
    private Bitrix24ClientInterface $client;
    private StateStorage            $state;
    private Logger                  $logger;

    /**
     * Имя исходного UF-поля.
     */
    private string $sourceField;

    /**
     * Имя целевого UF-поля.
     */
    private string $targetField;

    /**
     * Размер пачки сделок.
     */
    private int $batchSize;

    /**
     * Режим без записи (dry-run).
     */
    private bool $dryRun;

    /**
     * Флаг graceful shutdown (выставляется обработчиком сигналов).
     */
    private bool $shouldStop = false;

    /**
     * @param Bitrix24ClientInterface $client      HTTP-клиент Bitrix24
     * @param StateStorage            $state       Хранилище состояния
     * @param Logger                  $logger      Логгер
     * @param string                  $sourceField Имя исходного UF-поля
     * @param string                  $targetField Имя целевого UF-поля
     * @param int                     $batchSize   Размер пачки (макс. 50)
     * @param bool                    $dryRun      Если true - обновления не выполняются
     */
    public function __construct(
        Bitrix24ClientInterface $client,
        StateStorage $state,
        Logger $logger,
        string $sourceField,
        string $targetField,
        int $batchSize = 50,
        bool $dryRun = false
    ) {
        $this->client      = $client;
        $this->state       = $state;
        $this->logger      = $logger;
        $this->sourceField = $sourceField;
        $this->targetField = $targetField;
        $this->batchSize   = min($batchSize, 50);
        $this->dryRun      = $dryRun;
    }

    /**
     * Запросить остановку обработки после текущей пачки (вызывается из обработчика сигналов).
     */
    public function requestStop(): void
    {
        $this->shouldStop = true;
        $this->logger->warning('Получен сигнал остановки, завершу текущую пачку и выйду');
    }

    /**
     * Запустить обработку.
     *
     * @return bool true - если обработка завершена полностью; false - если прервана
     */
    public function run(): bool
    {
        $mode = $this->dryRun ? 'DRY-RUN' : 'LIVE';
        $this->logger->info("Старт обработки в режиме $mode", [
            'source_field' => $this->sourceField,
            'target_field' => $this->targetField,
            'cursor'       => $this->state->getLastProcessedId(),
        ]);

        while (true) {
            if ($this->shouldStop) {
                $this->logger->info('Обработка остановлена пользователем');
                $this->state->save();

                return false;
            }

            $cursor = $this->state->getLastProcessedId();
            $deals  = $this->fetchBatch($cursor);

            if ($deals === []) {
                $this->logger->info('Сделки закончились - обработка завершена');
                $this->state->markFinished();

                return true;
            }

            $this->processBatch($deals);

            // Сдвигаем курсор на максимальный ID пачки
            $maxId = 0;
            foreach ($deals as $deal) {
                $id = (int) $deal['ID'];
                if ($id > $maxId) {
                    $maxId = $id;
                }
            }
            $this->state->setLastProcessedId($maxId);
            $this->state->save();
        }
    }

    /**
     * Получить пачку сделок начиная с ID > $afterId.
     *
     * Используется фильтр >ID + сортировка ID ASC + start: -1 для отключения
     * подсчёта общего количества (ускоряет запрос на больших объёмах).
     *
     * @param int $afterId ID, начиная с которого (не включительно) запрашивать сделки
     *
     * @return list<array<string, mixed>> Массив сделок (минимум - ID, sourceField, targetField)
     */
    private function fetchBatch(int $afterId): array
    {
        $response = $this->client->call('crm.deal.list', [
            'order'  => ['ID' => 'ASC'],
            'filter' => ['>ID' => $afterId],
            'select' => ['ID', $this->sourceField, $this->targetField],
            'start'  => -1, // отключаем подсчёт total → быстрее
        ]);

        /** @var list<array<string, mixed>> $deals */
        $deals = $response['result'] ?? [];

        // crm.deal.list возвращает максимум 50 за раз - нам этого достаточно
        return array_slice($deals, 0, $this->batchSize);
    }

    /**
     * Обработать пачку сделок: отфильтровать кандидатов на обновление и выполнить batch.update.
     *
     * @param list<array<string, mixed>> $deals
     */
    private function processBatch(array $deals): void
    {
        if ($deals === []) {
            return;
        }

        $this->state->incrementStat('scanned', count($deals));

        $updates = [];
        foreach ($deals as $deal) {
            $id          = (int) $deal['ID'];
            $sourceValue = $deal[$this->sourceField] ?? null;
            $targetValue = $deal[$this->targetField] ?? null;

            // По договорённости: '0' считаем пустым → используем empty()
            if (empty($sourceValue)) {
                $this->state->incrementStat('skipped_empty_source');
                continue;
            }

            // Целевое поле должно быть пустым (не перезаписываем)
            if (!empty($targetValue)) {
                $this->state->incrementStat('skipped_target_filled');
                continue;
            }

            $updates[$id] = $sourceValue;
        }

        $firstId = (int) $deals[0]['ID'];
        $lastId  = (int) $deals[array_key_last($deals)]['ID'];
        $this->logger->info("Пачка ID [$firstId..$lastId]: всего {" . count($deals) . '}, к обновлению ' . count($updates));

        if ($updates === []) {
            return;
        }

        if ($this->dryRun) {
            $this->state->incrementStat('updated', count($updates));
            $this->logger->info('DRY-RUN: пропускаю фактическое обновление', ['ids' => array_keys($updates)]);

            return;
        }

        $this->executeBatchUpdate($updates);
    }

    /**
     * Выполнить batch-обновление сделок.
     *
     * @param array<int, mixed> $updates Карта dealId => значение для записи в targetField
     */
    private function executeBatchUpdate(array $updates): void
    {
        $commands = [];
        foreach ($updates as $dealId => $value) {
            $commands["upd_$dealId"] = [
                'method' => 'crm.deal.update',
                'params' => [
                    'id'     => $dealId,
                    'fields' => [
                        $this->targetField => $value,
                    ],
                ],
            ];
        }

        $response = $this->client->batch($commands);

        $errors       = $response['result_error'];
        $successCount = count($commands) - count($errors);

        $this->state->incrementStat('updated', $successCount);

        if ($errors !== []) {
            $this->state->incrementStat('errors', count($errors));
            foreach ($errors as $cmdId => $err) {
                // Извлекаем ID сделки из commandId вида "upd_12345"
                $dealId = (int) substr((string) $cmdId, 4);
                $this->logger->error("Ошибка обновления сделки $dealId", [
                    'command_id' => $cmdId,
                    'error'      => $err,
                ]);
            }
        }
    }
}
