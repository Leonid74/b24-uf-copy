<?php

declare(strict_types=1);

/**
 * Тесты основного цикла обработки сделок.
 */

namespace Leonid74\B24UfCopy\Tests;

use Leonid74\B24UfCopy\Bitrix24ClientInterface;
use Leonid74\B24UfCopy\DealProcessor;
use Leonid74\B24UfCopy\Logger;
use Leonid74\B24UfCopy\StateStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(DealProcessor::class)]
final class DealProcessorTest extends TestCase
{
    private string $tmpFile;
    private string $logDir;
    private StateStorage $state;
    private Logger $logger;

    /** @var Bitrix24ClientInterface&MockObject */
    private Bitrix24ClientInterface $client;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/b24uf_proc_' . uniqid('', true) . '.json';
        $this->logDir  = sys_get_temp_dir() . '/b24uf_logs_' . uniqid('', true);
        $this->state   = new StateStorage($this->tmpFile);
        $this->logger  = new Logger($this->logDir);
        $this->client  = $this->createMock(Bitrix24ClientInterface::class);
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpFile);
        foreach (glob($this->logDir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->logDir);
    }

    private function makeProcessor(bool $dryRun = false): DealProcessor
    {
        return new DealProcessor(
            client: $this->client,
            state: $this->state,
            logger: $this->logger,
            sourceField: 'UF_CRM_SOURCE',
            targetField: 'UF_CRM_TARGET',
            batchSize: 50,
            dryRun: $dryRun,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyBatchResponse(): array
    {
        return ['result' => [], 'result_error' => [], 'result_total' => []];
    }

    public function testRunReturnsTrueOnEmptyPortal(): void
    {
        $this->client
            ->expects(self::once())
            ->method('call')
            ->willReturn(['result' => []]);

        self::assertTrue($this->makeProcessor()->run());
    }

    public function testRunMarkesStateFinishedWhenComplete(): void
    {
        $this->client->method('call')->willReturn(['result' => []]);

        $this->makeProcessor()->run();

        self::assertTrue($this->state->isFinished());
    }

    public function testRequestStopReturnsFalseWithoutCallingApi(): void
    {
        $processor = $this->makeProcessor();
        $processor->requestStop();

        $this->client->expects(self::never())->method('call');

        self::assertFalse($processor->run());
        self::assertFalse($this->state->isFinished());
    }

    public function testDryRunCountsButDoesNotCallBatch(): void
    {
        $deals = [
            ['ID' => '1', 'UF_CRM_SOURCE' => 'value', 'UF_CRM_TARGET' => ''],
            ['ID' => '2', 'UF_CRM_SOURCE' => 'value2', 'UF_CRM_TARGET' => ''],
        ];

        $this->client->method('call')->willReturnOnConsecutiveCalls(
            ['result' => $deals],
            ['result' => []],
        );
        $this->client->expects(self::never())->method('batch');

        $this->makeProcessor(dryRun: true)->run();

        self::assertSame(2, $this->state->getStats()['updated']);
    }

    public function testSkipsDealsWithEmptySourceField(): void
    {
        $deals = [
            ['ID' => '1', 'UF_CRM_SOURCE' => '',   'UF_CRM_TARGET' => ''],
            ['ID' => '2', 'UF_CRM_SOURCE' => '0',  'UF_CRM_TARGET' => ''],
            ['ID' => '3', 'UF_CRM_SOURCE' => null, 'UF_CRM_TARGET' => ''],
        ];

        $this->client->method('call')->willReturnOnConsecutiveCalls(
            ['result' => $deals],
            ['result' => []],
        );
        $this->client->expects(self::never())->method('batch');

        $this->makeProcessor()->run();

        self::assertSame(3, $this->state->getStats()['skipped_empty_source']);
        self::assertSame(0, $this->state->getStats()['updated']);
    }

    public function testSkipsDealsWithAlreadyFilledTargetField(): void
    {
        $deals = [
            ['ID' => '1', 'UF_CRM_SOURCE' => 'val', 'UF_CRM_TARGET' => 'already_set'],
        ];

        $this->client->method('call')->willReturnOnConsecutiveCalls(
            ['result' => $deals],
            ['result' => []],
        );
        $this->client->expects(self::never())->method('batch');

        $this->makeProcessor()->run();

        self::assertSame(1, $this->state->getStats()['skipped_target_filled']);
        self::assertSame(0, $this->state->getStats()['updated']);
    }

    public function testUpdatesDealsWithValidFields(): void
    {
        $deals = [
            ['ID' => '10', 'UF_CRM_SOURCE' => 'src_val',  'UF_CRM_TARGET' => ''],
            ['ID' => '20', 'UF_CRM_SOURCE' => 'src_val2', 'UF_CRM_TARGET' => ''],
        ];

        $this->client->method('call')->willReturnOnConsecutiveCalls(
            ['result' => $deals],
            ['result' => []],
        );

        $this->client
            ->expects(self::once())
            ->method('batch')
            ->with(self::callback(static function (array $commands): bool {
                return isset($commands['upd_10'], $commands['upd_20'])
                    && $commands['upd_10']['method'] === 'crm.deal.update'
                    && $commands['upd_10']['params']['fields']['UF_CRM_TARGET'] === 'src_val'
                    && $commands['upd_20']['params']['fields']['UF_CRM_TARGET'] === 'src_val2';
            }))
            ->willReturn(['result' => ['upd_10' => true, 'upd_20' => true], 'result_error' => [], 'result_total' => []]);

        $this->makeProcessor()->run();

        self::assertSame(2, $this->state->getStats()['updated']);
        self::assertSame(0, $this->state->getStats()['errors']);
    }

    public function testCursorAdvancesToMaxIdInBatch(): void
    {
        $deals = [
            ['ID' => '5',  'UF_CRM_SOURCE' => 'val', 'UF_CRM_TARGET' => ''],
            ['ID' => '42', 'UF_CRM_SOURCE' => 'val', 'UF_CRM_TARGET' => ''],
        ];

        $this->client->method('call')->willReturnOnConsecutiveCalls(
            ['result' => $deals],
            ['result' => []],
        );
        $this->client->method('batch')->willReturn($this->emptyBatchResponse());

        $this->makeProcessor()->run();

        self::assertSame(42, $this->state->getLastProcessedId());
    }

    public function testBatchErrorsIncrementErrorStat(): void
    {
        $deals = [
            ['ID' => '1', 'UF_CRM_SOURCE' => 'val', 'UF_CRM_TARGET' => ''],
            ['ID' => '2', 'UF_CRM_SOURCE' => 'val', 'UF_CRM_TARGET' => ''],
        ];

        $this->client->method('call')->willReturnOnConsecutiveCalls(
            ['result' => $deals],
            ['result' => []],
        );
        $this->client->method('batch')->willReturn([
            'result'       => ['upd_2' => true],
            'result_error' => ['upd_1' => 'ACCESS_DENIED'],
            'result_total' => [],
        ]);

        $this->makeProcessor()->run();

        self::assertSame(1, $this->state->getStats()['errors']);
        self::assertSame(1, $this->state->getStats()['updated']);
    }

    public function testMixedDealsFilteredCorrectly(): void
    {
        $deals = [
            ['ID' => '1', 'UF_CRM_SOURCE' => 'val',  'UF_CRM_TARGET' => ''],        // обновляем
            ['ID' => '2', 'UF_CRM_SOURCE' => '',      'UF_CRM_TARGET' => ''],        // пустой source
            ['ID' => '3', 'UF_CRM_SOURCE' => 'val',  'UF_CRM_TARGET' => 'filled'],  // target заполнен
            ['ID' => '4', 'UF_CRM_SOURCE' => 'val2', 'UF_CRM_TARGET' => ''],        // обновляем
        ];

        $this->client->method('call')->willReturnOnConsecutiveCalls(
            ['result' => $deals],
            ['result' => []],
        );
        $this->client->method('batch')
            ->willReturn(['result' => ['upd_1' => true, 'upd_4' => true], 'result_error' => [], 'result_total' => []]);

        $this->makeProcessor()->run();

        self::assertSame(4, $this->state->getStats()['scanned']);
        self::assertSame(2, $this->state->getStats()['updated']);
        self::assertSame(1, $this->state->getStats()['skipped_empty_source']);
        self::assertSame(1, $this->state->getStats()['skipped_target_filled']);
    }
}
