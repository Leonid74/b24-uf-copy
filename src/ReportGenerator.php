<?php

declare(strict_types=1);

/**
 * Генератор простого HTML-отчёта по результатам обработки.
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

use RuntimeException;

final class ReportGenerator
{
    /**
     * Сгенерировать HTML-отчёт и сохранить в файл.
     *
     * @param string               $outputPath  Путь к итоговому HTML-файлу
     * @param array<string, mixed> $stateData   Снимок состояния StateStorage
     * @param string               $sourceField Имя исходного UF-поля
     * @param string               $targetField Имя целевого UF-поля
     * @param bool                 $dryRun      Был ли запуск в режиме dry-run
     * @param bool                 $completed   Завершилась ли обработка полностью
     */
    public function generate(
        string $outputPath,
        array $stateData,
        string $sourceField,
        string $targetField,
        bool $dryRun,
        bool $completed
    ): void {
        $stats     = $stateData['stats']             ?? [];
        $startedAt = $stateData['started_at']        ?? '—';
        $updatedAt = $stateData['updated_at']        ?? '—';
        $lastId    = $stateData['last_processed_id'] ?? 0;

        $scanned       = (int) ($stats['scanned'] ?? 0);
        $updated       = (int) ($stats['updated'] ?? 0);
        $skippedEmpty  = (int) ($stats['skipped_empty_source'] ?? 0);
        $skippedFilled = (int) ($stats['skipped_target_filled'] ?? 0);
        $errors        = (int) ($stats['errors'] ?? 0);

        $statusBadge = $completed
            ? '<span class="badge badge-success">Завершено</span>'
            : '<span class="badge badge-warning">Прервано</span>';

        $modeBadge = $dryRun
            ? '<span class="badge badge-info">DRY-RUN</span>'
            : '<span class="badge badge-primary">LIVE</span>';

        $html = $this->renderTemplate([
            'status_badge'   => $statusBadge,
            'mode_badge'     => $modeBadge,
            'started_at'     => htmlspecialchars((string) $startedAt, ENT_QUOTES, 'UTF-8'),
            'updated_at'     => htmlspecialchars((string) $updatedAt, ENT_QUOTES, 'UTF-8'),
            'last_id'        => (int) $lastId,
            'source_field'   => htmlspecialchars($sourceField, ENT_QUOTES, 'UTF-8'),
            'target_field'   => htmlspecialchars($targetField, ENT_QUOTES, 'UTF-8'),
            'scanned'        => $scanned,
            'updated'        => $updated,
            'skipped_empty'  => $skippedEmpty,
            'skipped_filled' => $skippedFilled,
            'errors'         => $errors,
            'generated_at'   => date('Y-m-d H:i:s'),
        ]);

        $dir = dirname($outputPath);
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new RuntimeException("Не удалось создать каталог отчёта: $dir");
        }

        if (file_put_contents($outputPath, $html) === false) {
            throw new RuntimeException("Не удалось записать HTML-отчёт: $outputPath");
        }
    }

    /**
     * Подставить значения в HTML-шаблон.
     *
     * @param array<string, mixed> $data
     */
    private function renderTemplate(array $data): string
    {
        return <<<HTML
            <!DOCTYPE html>
            <html lang="ru">
            <head>
            <meta charset="UTF-8">
            <title>Отчёт о копировании UF-полей</title>
            <style>
              body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f4f6f9; margin: 0; padding: 40px; color: #333; }
              .container { max-width: 720px; margin: 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); padding: 32px; }
              h1 { margin-top: 0; font-size: 24px; }
              .meta { color: #888; font-size: 13px; margin-bottom: 24px; }
              .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; margin-right: 6px; }
              .badge-success { background: #d4edda; color: #155724; }
              .badge-warning { background: #fff3cd; color: #856404; }
              .badge-info { background: #d1ecf1; color: #0c5460; }
              .badge-primary { background: #cce5ff; color: #004085; }
              table { width: 100%; border-collapse: collapse; margin-top: 16px; }
              th, td { padding: 10px 12px; border-bottom: 1px solid #eee; text-align: left; }
              th { background: #fafafa; font-weight: 600; color: #555; width: 40%; }
              .num { font-variant-numeric: tabular-nums; font-weight: 600; }
              .num-updated { color: #28a745; }
              .num-errors { color: #dc3545; }
              .footer { margin-top: 24px; padding-top: 16px; border-top: 1px solid #eee; color: #999; font-size: 12px; }
            </style>
            </head>
            <body>
            <div class="container">
              <h1>Отчёт о копировании UF-полей в сделках Bitrix24</h1>
              <div class="meta">{$data['status_badge']} {$data['mode_badge']}</div>

              <table>
                <tr><th>Исходное поле</th><td><code>{$data['source_field']}</code></td></tr>
                <tr><th>Целевое поле</th><td><code>{$data['target_field']}</code></td></tr>
                <tr><th>Начало обработки</th><td>{$data['started_at']}</td></tr>
                <tr><th>Последнее обновление состояния</th><td>{$data['updated_at']}</td></tr>
                <tr><th>Последний обработанный ID</th><td class="num">{$data['last_id']}</td></tr>
              </table>

              <h2 style="margin-top: 32px; font-size: 18px;">Статистика</h2>
              <table>
                <tr><th>Просмотрено сделок</th><td class="num">{$data['scanned']}</td></tr>
                <tr><th>Обновлено</th><td class="num num-updated">{$data['updated']}</td></tr>
                <tr><th>Пропущено (исходное пустое)</th><td class="num">{$data['skipped_empty']}</td></tr>
                <tr><th>Пропущено (целевое уже заполнено)</th><td class="num">{$data['skipped_filled']}</td></tr>
                <tr><th>Ошибок</th><td class="num num-errors">{$data['errors']}</td></tr>
              </table>

              <div class="footer">Отчёт сгенерирован {$data['generated_at']}</div>
            </div>
            </body>
            </html>
            HTML;
    }
}
