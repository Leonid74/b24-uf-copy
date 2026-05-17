<?php

declare(strict_types=1);

/**
 * Контракт HTTP-клиента Bitrix24.
 *
 * Позволяет подменять реализацию в тестах.
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

interface Bitrix24ClientInterface
{
    /**
     * Одиночный вызов метода Bitrix24.
     *
     * @param string               $method Имя метода (например crm.deal.list)
     * @param array<string, mixed> $params Параметры
     *
     * @return array<string, mixed> Полный декодированный ответ Bitrix24
     */
    public function call(string $method, array $params = []): array;

    /**
     * Пакетный вызов нескольких методов одним HTTP-запросом.
     *
     * @param array<string, array{method: string, params: array<string, mixed>}> $commands Карта commandId => [method, params]
     * @param bool                                                               $halt     Прерывать ли пакет при первой ошибке
     *
     * @return array{result: array<string, mixed>, result_error: array<string, mixed>, result_total: array<string, mixed>}
     */
    public function batch(array $commands, bool $halt = false): array;
}
