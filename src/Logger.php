<?php

declare(strict_types=1);

/**
 * Простой логгер: пишет одновременно в stdout и в файл логов суточной ротации.
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

use RuntimeException;

final class Logger
{
    /**
     * Каталог для лог-файлов.
     */
    private string $logDir;

    /**
     * Указатель на текущий лог-файл (открывается лениво).
     *
     * @var resource|null
     */
    private $fileHandle = null;

    /**
     * Текущая дата лог-файла (для суточной ротации).
     */
    private string $currentDate = '';

    /**
     * @param string $logDir Абсолютный путь к каталогу логов
     */
    public function __construct(string $logDir)
    {
        $this->logDir = rtrim($logDir, '/');
        if (!is_dir($this->logDir) && !mkdir($this->logDir, 0o755, true) && !is_dir($this->logDir)) {
            throw new RuntimeException("Не удалось создать каталог логов: $this->logDir");
        }
    }

    /**
     * Информационное сообщение.
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    /**
     * Предупреждение.
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('WARN', $message, $context);
    }

    /**
     * Ошибка.
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    /**
     * Закрыть открытый файл лога.
     */
    public function __destruct()
    {
        if (is_resource($this->fileHandle)) {
            fclose($this->fileHandle);
        }
    }

    /**
     * Запись строки в лог.
     *
     * @param string               $level   Уровень логирования
     * @param string               $message Сообщение
     * @param array<string, mixed> $context Дополнительный контекст
     */
    private function log(string $level, string $message, array $context = []): void
    {
        $timestamp  = date('Y-m-d H:i:s');
        $contextStr = $context !== []
            ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : '';

        $line = "[$timestamp] [$level] $message$contextStr" . PHP_EOL;

        echo $line;

        $this->writeToFile($line);
    }

    /**
     * Запись строки в текущий файл лога (с суточной ротацией).
     */
    private function writeToFile(string $line): void
    {
        $today = date('Y-m-d');
        if ($today !== $this->currentDate) {
            if (is_resource($this->fileHandle)) {
                fclose($this->fileHandle);
            }
            $path   = $this->logDir . "/run-$today.log";
            $handle = @fopen($path, 'ab');
            if ($handle === false) {
                // Файловая система недоступна - продолжаем без записи в файл
                $this->fileHandle = null;

                return;
            }
            $this->fileHandle  = $handle;
            $this->currentDate = $today;
        }

        if (is_resource($this->fileHandle)) {
            @fwrite($this->fileHandle, $line);
            @fflush($this->fileHandle);
        }
    }
}
