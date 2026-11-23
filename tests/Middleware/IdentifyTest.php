<?php

/**
 * OAI-PMH 2.0 Data Provider
 * Copyright (C) 2026 Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace OCC\OaiPmh2\Tests\Middleware;

use OCC\OaiPmh2\Middleware\Identify;
use OCC\OaiPmh2\Tests\TestCase;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Identify::class)]
final class IdentifyTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        $this->useDatabase();
    }

    #[Test]
    public function itDescribesTheRepository(): void
    {
        $xpath = $this->requestXml('verb=Identify');

        $this->assertSame([], self::errors($xpath));
        $this->assertSame('Test Repository', self::text($xpath, '//oai:Identify/oai:repositoryName'));
        $this->assertSame('http://example.org/oai', self::text($xpath, '//oai:Identify/oai:baseURL'));
        $this->assertSame('2.0', self::text($xpath, '//oai:Identify/oai:protocolVersion'));
        $this->assertSame('admin@example.org', self::text($xpath, '//oai:Identify/oai:adminEmail'));
        $this->assertSame('transient', self::text($xpath, '//oai:Identify/oai:deletedRecord'));
        $this->assertSame('YYYY-MM-DDThh:mm:ssZ', self::text($xpath, '//oai:Identify/oai:granularity'));
    }

    #[Test]
    public function itReportsTheEarliestDatestamp(): void
    {
        $this->addRecord('rec1', lastChanged: '2026-03-01T12:00:00Z');
        $this->addRecord('rec2', lastChanged: '2025-12-31T23:59:59Z');
        $this->addRecord('rec3', lastChanged: '2026-01-01T00:00:00+01:00');

        $xpath = $this->requestXml('verb=Identify');

        $this->assertSame('2025-12-31T23:00:00Z', self::text($xpath, '//oai:Identify/oai:earliestDatestamp'));
    }

    #[Test]
    public function itReportsTheConfiguredDeletedRecordSupport(): void
    {
        $this->useConfiguration(['deletedRecords' => 'persistent']);

        $xpath = $this->requestXml('verb=Identify');

        $this->assertSame('persistent', self::text($xpath, '//oai:Identify/oai:deletedRecord'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function baseUrlProvider(): array
    {
        return [
            'default HTTP port' => ['http://example.org:80/oai', 'http://example.org/oai'],
            'default HTTPS port' => ['https://example.org:443/oai', 'https://example.org/oai'],
            'custom HTTP port' => ['http://example.org:8080/oai', 'http://example.org:8080/oai'],
            'custom HTTPS port' => ['https://example.org:8443/oai/index.php', 'https://example.org:8443/oai/index.php'],
            'IPv6 host' => ['http://[::1]:8080/oai', 'http://[::1]:8080/oai'],
            'user information' => ['http://user:secret@example.org/oai', 'http://example.org/oai']
        ];
    }

    #[Test]
    #[DataProvider('baseUrlProvider')]
    public function itReportsTheBaseUrlWithPort(string $requestUri, string $baseUrl): void
    {
        $xpath = $this->requestXml('verb=Identify', uri: $requestUri);

        $this->assertSame($baseUrl, self::text($xpath, '//oai:Identify/oai:baseURL'));
        $this->assertSame($baseUrl, self::text($xpath, '/oai:OAI-PMH/oai:request'));
    }

    #[Test]
    public function itRejectsAdditionalArguments(): void
    {
        $xpath = $this->requestXml('verb=Identify&metadataPrefix=oai_dc');

        $this->assertOaiError('badArgument', $xpath);
        $this->assertSame(0, $xpath->query('//oai:Identify')?->length);
    }
}
