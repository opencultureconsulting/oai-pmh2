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

use OCC\OaiPmh2\Middleware\GetRecord;
use OCC\OaiPmh2\Tests\TestCase;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GetRecord::class)]
final class GetRecordTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        $this->useDatabase();
    }

    #[Test]
    public function itReturnsTheRecord(): void
    {
        $this->addSet('coll:a', 'Collection A');
        $this->addRecord('oai:example.org:1', lastChanged: '2026-02-03T04:05:06Z', sets: ['coll:a', 'other']);

        $xpath = $this->requestXml('verb=GetRecord&metadataPrefix=oai_dc&identifier=oai:example.org:1');

        $this->assertSame([], self::errors($xpath));
        $header = '//oai:GetRecord/oai:record/oai:header';
        $this->assertSame('oai:example.org:1', self::text($xpath, $header . '/oai:identifier'));
        $this->assertSame('2026-02-03T04:05:06Z', self::text($xpath, $header . '/oai:datestamp'));
        // The set spec must be returned, not the set name.
        $this->assertSame(['coll:a', 'other'], self::texts($xpath, $header . '/oai:setSpec'));
        $this->assertSame(0, $xpath->query($header . '/@status')?->length);
        $xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');
        $this->assertSame('Test', self::text($xpath, '//oai:GetRecord/oai:record/oai:metadata/dc:dc/dc:title'));
    }

    #[Test]
    public function itMarksDeletedRecords(): void
    {
        $this->addRecord('rec1', content: null);

        $xpath = $this->requestXml('verb=GetRecord&metadataPrefix=oai_dc&identifier=rec1');

        $this->assertSame([], self::errors($xpath));
        $this->assertSame('deleted', self::text($xpath, '//oai:GetRecord/oai:record/oai:header/@status'));
        $this->assertSame(0, $xpath->query('//oai:GetRecord/oai:record/oai:metadata')?->length);
    }

    #[Test]
    public function itReportsUnknownIdentifiers(): void
    {
        $this->addRecord('rec1');

        $xpath = $this->requestXml('verb=GetRecord&metadataPrefix=oai_dc&identifier=rec2');

        $this->assertOaiError('idDoesNotExist', $xpath);
    }

    #[Test]
    public function itReportsFormatsNotAvailableForTheRecord(): void
    {
        $this->addRecord('rec1', format: 'mods');

        $xpath = $this->requestXml('verb=GetRecord&metadataPrefix=oai_dc&identifier=rec1');

        $this->assertOaiError('cannotDisseminateFormat', $xpath);
    }

    #[Test]
    public function itReportsUnsupportedFormats(): void
    {
        $this->addRecord('rec1');

        $xpath = $this->requestXml('verb=GetRecord&metadataPrefix=marc&identifier=rec1');

        $this->assertOaiError('cannotDisseminateFormat', $xpath);
    }

    #[Test]
    public function itEchoesTheArgumentsEscapedOnlyOnce(): void
    {
        $xpath = $this->requestXml('verb=GetRecord&metadataPrefix=oai_dc&identifier=a%26b%22c%3Cd');

        $this->assertOaiError('idDoesNotExist', $xpath);
        $this->assertSame('a&b"c<d', self::text($xpath, '/oai:OAI-PMH/oai:request/@identifier'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badArgumentProvider(): array
    {
        return [
            'missing identifier' => ['verb=GetRecord&metadataPrefix=oai_dc'],
            'missing metadataPrefix' => ['verb=GetRecord&identifier=rec1'],
            'additional argument' => ['verb=GetRecord&metadataPrefix=oai_dc&identifier=rec1&set=a'],
            'resumptionToken' => ['verb=GetRecord&resumptionToken=abc']
        ];
    }

    #[Test]
    #[DataProvider('badArgumentProvider')]
    public function itRejectsIllegalArguments(string $query): void
    {
        $this->addRecord('rec1');

        $this->assertOaiError('badArgument', $this->requestXml($query));
    }
}
