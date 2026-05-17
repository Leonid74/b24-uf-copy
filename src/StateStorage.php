<?php

/**
 * Хранилище состояния обхода сделок.
 *
 * Состояние сохраняется в JSON-файл атомарно (через временный файл + rename),
 * чтобы при внезапном завершении скрипта не получить повреждённый файл.
 *
 * PHP version 8.2+
 *
 * @version   1.0
 *
 * @author    Leonid Sheikman <Leonid74>
 * @copyright 2026 Leonid Sheikman
 * @license   The 3-Clause BSD License (https://opensource.org/license/bsd-3-clause)
 */

declare(strict_types=1);

namespace Leonid74\B24UfCopy;

use JsonException;
use Random\RandomException;
use RuntimeException;

final class StateStorage
{
    /**
     * Путь к файлу состояния.
     */
    private string $filePath;

    /**
     * Текущее состояние в памяти.
     *
     * @var array<string, mixed>
     */
    private array $state;

    /**
     * @param string $filePath Абсолютный путь к JSON-файлу состояния
     */
    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
        $this->state    = $this->loadOrInitialize();
    }

    /**
     * Полный сброс состояния (используется при --restart).
     */
    public function reset(): void
    {
        $this->state = $this->createInitialState();
        $this->save();
    }

    /**
     * Получить ID последней успешно обработанной сделки (курсор).
     */
    public function getLastProcessedId(): int
    {
        return (int) ($this->state['last_processed_id'] ?? 0);
    }

    /**
     * Установить ID последней обработанной сделки.
     */
    public function setLastProcessedId(int $id): void
    {
        $this->state['last_processed_id'] = $id;
        $this->state['updated_at']        = date('c');
    }

    /**
     * Инкрементировать счётчик статистики.
     *
     * @param string $key   Ключ счётчика (scanned, updated, ...)
     * @param int    $delta Прирост
     */
    public function incrementStat(string $key, int $delta = 1): void
    {
        if (!isset($this->state['stats'][$key])) {
            $this->state['stats'][$key] = 0;
        }
        $this->state['stats'][$key] += $delta;
    }

    /**
     * Получить текущую статистику.
     *
     * @return array<string, int>
     */
    public function getStats(): array
    {
        /** @var array<string, int> */
        return $this->state['stats'] ?? [];
    }

    /**
     * Получить время начала обработки.
     */
    public function getStartedAt(): string
    {
        return (string) ($this->state['started_at'] ?? date('c'));
    }

    /**
     * Получить флаг завершения.
     */
    public function isFinished(): bool
    {
        return (bool) ($this->state['finished'] ?? false);
    }

    /**
     * Пометить обработку как завершённую.
     */
    public function markFinished(): void
    {
        $this->state['finished']    = true;
        $this->state['finished_at'] = date('c');
        $this->save();
    }

    /**
     * Атомарно сохранить состояние на диск.
     *
     * Запись идёт во временный файл в том же каталоге, затем выполняется rename().
     * На одной файловой системе - rename() атомарная операция в POSIX-системах.
     *
     * @throws RuntimeException При невозможности записать файл
     * @throws JsonException    При ошибках декодирования
     * @throws RandomException  При ошибках random_bytes
     */
    public function save(): void
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new RuntimeException("Не удалось создать каталог состояния: $dir");
        }

        $tmpPath = $dir . '/.progress.' . bin2hex(random_bytes(6)) . '.tmp';

        $json = json_encode(
            $this->state,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        if (file_put_contents($tmpPath, $json, LOCK_EX) === false) {
            throw new RuntimeException("Не удалось записать временный файл состояния: $tmpPath");
        }

        if (!rename($tmpPath, $this->filePath)) {
            @unlink($tmpPath);
            throw new RuntimeException("Не удалось переименовать файл состояния в: $this->filePath");
        }
    }

    /**
     * Получить полный снимок состояния (для отчётов).
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return $this->state;
    }

    /**
     * Загружает состояние из файла или создаёт начальную структуру.
     *
     * @return array<string, mixed>
     */
    private function loadOrInitialize(): array
    {
        if (!is_file($this->filePath)) {
            return $this->createInitialState();
        }

        $raw = @file_get_contents($this->filePath);
        if ($raw === false || $raw === '') {
            return $this->createInitialState();
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                return $this->createInitialState();
            }

            return $data;
        } catch (JsonException) {
            // Повреждённый файл - начинаем заново, но сохраняем повреждённый под другим именем
            @rename($this->filePath, $this->filePath . '.corrupted.' . time());

            return $this->createInitialState();
        }
    }

    /**
     * Возвращает начальную структуру состояния.
     *
     * @return array<string, mixed>
     */
    private function createInitialState(): array
    {
        return [
            'started_at'        => date('c'),
            'updated_at'        => date('c'),
            'last_processed_id' => 0,
            'stats'             => [
                'scanned'               => 0,
                'updated'               => 0,
                'skipped_empty_source'  => 0,
                'skipped_target_filled' => 0,
                'errors'                => 0,
            ],
            'finished'          => false,
        ];
    }
}
