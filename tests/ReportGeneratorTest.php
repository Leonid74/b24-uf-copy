<?php

declare(strict_types=1);

/**
 * Тесты генератора HTML-отчётов.
 */

namespace Leonid74\B24UfCopy\Tests;

use Leonid74\B24UfCopy\ReportGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReportGenerator::class)]
final class ReportGeneratorTest extends TestCase
{
    private string $tmpDir;
    private string $outputFile;

    protected function setUp(): void
    {
        $this->tmpDir     = sys_get_temp_dir() . '/b24uf_report_' . uniqid('', true);
        $this->outputFile = $this->tmpDir . '/report.html';
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function makeSnapshot(array $overrides = []): array
    {
        return array_merge([
            'started_at'        => '2026-01-01T10:00:00+00:00',
            'updated_at'        => '2026-01-01T11:00:00+00:00',
            'last_processed_id' => 500,
            'stats'             => [
                'scanned'               => 1000,
                'updated'               => 800,
                'skipped_empty_source'  => 100,
                'skipped_target_filled' => 100,
                'errors'                => 5,
            ],
            'finished'          => true,
        ], $overrides);
    }

    private function generate(
        bool $dryRun = false,
        bool $completed = true,
        string $sourceField = 'UF_CRM_SOURCE',
        string $targetField = 'UF_CRM_TARGET',
    ): string {
        (new ReportGenerator())->generate(
            outputPath: $this->outputFile,
            stateData: $this->makeSnapshot(),
            sourceField: $sourceField,
            targetField: $targetField,
            dryRun: $dryRun,
            completed: $completed,
        );

        return (string) file_get_contents($this->outputFile);
    }

    public function testGeneratesHtmlFile(): void
    {
        $this->generate();

        self::assertFileExists($this->outputFile);
    }

    public function testOutputIsValidHtmlDocument(): void
    {
        $html = $this->generate();

        self::assertStringContainsString('<!DOCTYPE html>', $html);
        self::assertStringContainsString('<html', $html);
        self::assertStringContainsString('</html>', $html);
        self::assertStringContainsString('charset="UTF-8"', $html);
    }

    public function testHtmlContainsFieldNames(): void
    {
        $html = $this->generate(sourceField: 'UF_CRM_SRC', targetField: 'UF_CRM_DST');

        self::assertStringContainsString('UF_CRM_SRC', $html);
        self::assertStringContainsString('UF_CRM_DST', $html);
    }

    public function testHtmlContainsStatistics(): void
    {
        $html = $this->generate();

        self::assertStringContainsString('1000', $html);  // scanned
        self::assertStringContainsString('800', $html);   // updated
        self::assertStringContainsString('100', $html);   // skipped
        self::assertStringContainsString('5', $html);     // errors
        self::assertStringContainsString('500', $html);   // last_processed_id
    }

    public function testCompletedBadge(): void
    {
        $html = $this->generate(completed: true);

        self::assertStringContainsString('Завершено', $html);
        self::assertStringNotContainsString('Прервано', $html);
    }

    public function testInterruptedBadge(): void
    {
        $html = $this->generate(completed: false);

        self::assertStringContainsString('Прервано', $html);
        self::assertStringNotContainsString('Завершено', $html);
    }

    public function testDryRunBadge(): void
    {
        $html = $this->generate(dryRun: true);

        self::assertStringContainsString('DRY-RUN', $html);
        self::assertStringNotContainsString('LIVE', $html);
    }

    public function testLiveBadge(): void
    {
        $html = $this->generate(dryRun: false);

        self::assertStringContainsString('LIVE', $html);
        self::assertStringNotContainsString('DRY-RUN', $html);
    }

    public function testCreatesDirectoryIfNotExists(): void
    {
        $nestedFile = $this->tmpDir . '/nested/sub/report.html';

        (new ReportGenerator())->generate(
            outputPath: $nestedFile,
            stateData: $this->makeSnapshot(),
            sourceField: 'UF_A',
            targetField: 'UF_B',
            dryRun: false,
            completed: true,
        );

        self::assertFileExists($nestedFile);
    }

    public function testFieldNamesAreXssEscaped(): void
    {
        $html = $this->generate(
            sourceField: '<script>alert(1)</script>',
            targetField: 'UF_B',
        );

        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }
}
