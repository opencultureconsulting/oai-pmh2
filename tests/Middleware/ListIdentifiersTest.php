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

use OCC\OaiPmh2\Middleware;
use OCC\OaiPmh2\Middleware\ListIdentifiers;
use OCC\OaiPmh2\Middleware\ListRecords;
use OCC\OaiPmh2\Tests\TestCase;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(ListIdentifiers::class)]
#[CoversClass(ListRecords::class)]
#[CoversClass(Middleware::class)]
final class ListIdentifiersTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        $this->useDatabase();
    }

    /**
     * Request a complete list by following all resumption tokens.
     *
     * @param string $query The initial query string
     *
     * @return array{identifiers: list<string>, pages: list<array{cursor: ?string, completeListSize: ?string}>}
     */
    private function harvest(string $query): array
    {
        $identifiers = [];
        $pages = [];
        $verb = str_starts_with($query, 'verb=ListRecords') ? 'ListRecords' : 'ListIdentifiers';
        do {
            $xpath = $this->requestXml($query);
            $this->assertSame([], self::errors($xpath));
            array_push($identifiers, ...self::texts($xpath, '//oai:header/oai:identifier'));
            $pages[] = [
                'cursor' => self::text($xpath, '//oai:resumptionToken/@cursor'),
                'completeListSize' => self::text($xpath, '//oai:resumptionToken/@completeListSize')
            ];
            $token = self::text($xpath, '//oai:resumptionToken');
            $query = 'verb=' . $verb . '&resumptionToken=' . $token;
        } while ($token !== null && $token !== '');
        return ['identifiers' => $identifiers, 'pages' => $pages];
    }

    #[Test]
    public function itListsAllRecordsInStableOrderAcrossPages(): void
    {
        foreach (['rec-c', 'rec-e', 'rec-a', 'rec-d', 'rec-b'] as $identifier) {
            $this->addRecord($identifier);
        }

        $result = $this->harvest('verb=ListIdentifiers&metadataPrefix=oai_dc');

        $this->assertSame(['rec-a', 'rec-b', 'rec-c', 'rec-d', 'rec-e'], $result['identifiers']);
        $this->assertSame(
            [
                ['cursor' => '0', 'completeListSize' => '5'],
                ['cursor' => '2', 'completeListSize' => '5'],
                ['cursor' => '4', 'completeListSize' => '5']
            ],
            $result['pages']
        );
    }

    #[Test]
    public function itHasNoResumptionTokenForCompleteLists(): void
    {
        $this->addRecord('rec1');
        $this->addRecord('rec2');

        $xpath = $this->requestXml('verb=ListIdentifiers&metadataPrefix=oai_dc');

        $this->assertSame(['rec1', 'rec2'], self::texts($xpath, '//oai:header/oai:identifier'));
        $this->assertSame(0, $xpath->query('//oai:resumptionToken')?->length);
    }

    #[Test]
    public function itOnlyListsRecordsOfTheRequestedFormat(): void
    {
        $this->addRecord('rec1');
        $this->addRecord('rec2', format: 'mods');

        $xpath = $this->requestXml('verb=ListIdentifiers&metadataPrefix=mods');

        $this->assertSame(['rec2'], self::texts($xpath, '//oai:header/oai:identifier'));
    }

    #[Test]
    public function itFiltersBySetIncludingSubsets(): void
    {
        $this->addRecord('rec1', sets: ['coll:a']);
        $this->addRecord('rec2', sets: ['other']);
        $this->addRecord('rec3');
        $this->addRecord('rec4', sets: ['coll', 'coll:a']);
        $this->addRecord('rec5', sets: ['coll']);
        $this->addRecord('rec6', sets: ['collection']);

        $result = $this->harvest('verb=ListIdentifiers&metadataPrefix=oai_dc&set=coll');

        // A record in several matching sets must only be listed (and counted) once.
        $this->assertSame(['rec1', 'rec4', 'rec5'], $result['identifiers']);
        $this->assertSame('3', $result['pages'][0]['completeListSize']);
        $this->assertSame(
            ['rec1', 'rec4'],
            $this->harvest('verb=ListIdentifiers&metadataPrefix=oai_dc&set=coll:a')['identifiers']
        );
    }

    #[Test]
    public function itDoesNotTreatUnderscoresInSetSpecsAsWildcards(): void
    {
        $this->addSet('a_b');
        $this->addRecord('rec1', sets: ['axb:c']);

        $xpath = $this->requestXml('verb=ListIdentifiers&metadataPrefix=oai_dc&set=a_b');

        $this->assertOaiError('noRecordsMatch', $xpath);
    }

    #[Test]
    public function itReportsMissingSetSupport(): void
    {
        $this->addRecord('rec1');

        $xpath = $this->requestXml('verb=ListIdentifiers&metadataPrefix=oai_dc&set=coll');

        $this->assertOaiError('noSetHierarchy', $xpath);
    }

    #[Test]
    public function itListsSetSpecsInHeaders(): void
    {
        $this->addSet('coll:a', 'Collection A');
        $this->addRecord('rec1', sets: ['coll:a']);

        $xpath = $this->requestXml('verb=ListIdentifiers&metadataPrefix=oai_dc');

        $this->assertSame(['coll:a'], self::texts($xpath, '//oai:header/oai:setSpec'));
    }

    #[Test]
    public function listRecordsIncludesMetadataExceptForDeletedRecords(): void
    {
        $this->addRecord('rec1');
        $this->addRecord('rec2', content: null);

        $xpath = $this->requestXml('verb=ListRecords&metadataPrefix=oai_dc');

        $this->assertSame(['rec1', 'rec2'], self::texts($xpath, '//oai:ListRecords/oai:record/oai:header/oai:identifier'));
        $this->assertSame(['deleted'], self::texts($xpath, '//oai:ListRecords/oai:record/oai:header/@status'));
        $this->assertSame(1, $xpath->query('//oai:ListRecords/oai:record/oai:metadata')?->length);
        $this->assertSame(
            'rec1',
            self::text($xpath, '//oai:ListRecords/oai:record[oai:metadata]/oai:header/oai:identifier')
        );
    }

    #[Test]
    public function listIdentifiersNeverIncludesMetadata(): void
    {
        $this->addRecord('rec1');
        $this->addRecord('rec2', content: null);

        $xpath = $this->requestXml('verb=ListIdentifiers&metadataPrefix=oai_dc');

        $this->assertSame(0, $xpath->query('//oai:record | //oai:metadata')?->length);
        $this->assertSame(['deleted'], self::texts($xpath, '//oai:ListIdentifiers/oai:header/@status'));
    }

    #[Test]
    public function listRecordsFollowsResumptionTokens(): void
    {
        foreach (['rec1', 'rec2', 'rec3'] as $identifier) {
            $this->addRecord($identifier);
        }

        $result = $this->harvest('verb=ListRecords&metadataPrefix=oai_dc');

        $this->assertSame(['rec1', 'rec2', 'rec3'], $result['identifiers']);
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function datestampProvider(): array
    {
        return [
            'until day includes the whole day' => ['until=2026-11-21', ['rec1', 'rec2']],
            'from and until the same day' => ['from=2026-11-21&until=2026-11-21', ['rec2']],
            'from day' => ['from=2026-11-22', ['rec3']],
            'until seconds' => ['until=2026-11-21T23:00:00Z', ['rec1']],
            'from and until seconds are inclusive' => [
                'from=2026-11-21T23:30:00Z&until=2026-11-22T00:00:00Z',
                ['rec2', 'rec3']
            ],
            'leap day' => ['from=2024-02-29', ['rec1', 'rec2', 'rec3']]
        ];
    }

    /**
     * @param list<string> $identifiers
     */
    #[Test]
    #[DataProvider('datestampProvider')]
    public function itFiltersByDatestamps(string $arguments, array $identifiers): void
    {
        $this->addRecord('rec1', lastChanged: '2026-11-20T10:00:00Z');
        $this->addRecord('rec2', lastChanged: '2026-11-21T23:30:00Z');
        $this->addRecord('rec3', lastChanged: '2026-11-22T00:00:00Z');
        $this->useConfiguration(['maxRecords' => 10]);

        $xpath = $this->requestXml('verb=ListIdentifiers&metadataPrefix=oai_dc&' . $arguments);

        $this->assertSame([], self::errors($xpath));
        $this->assertSame($identifiers, self::texts($xpath, '//oai:header/oai:identifier'));
    }

    #[Test]
    public function itKeepsTheExpandedDatestampInResumptionTokens(): void
    {
        $this->addRecord('rec1', lastChanged: '2026-11-21T01:00:00Z');
        $this->addRecord('rec2', lastChanged: '2026-11-21T02:00:00Z');
        $this->addRecord('rec3', lastChanged: '2026-11-21T23:59:59Z');
        $this->addRecord('rec4', lastChanged: '2026-11-22T00:00:00Z');

        $result = $this->harvest('verb=ListIdentifiers&metadataPrefix=oai_dc&until=2026-11-21');

        $this->assertSame(['rec1', 'rec2', 'rec3'], $result['identifiers']);
    }

    #[Test]
    public function itEchoesTheOriginalDatestamps(): void
    {
        $this->addRecord('rec1', lastChanged: '2026-11-20T10:00:00Z');

        $xpath = $this->requestXml('verb=ListIdentifiers&metadataPrefix=oai_dc&until=2026-11-21');

        $this->assertSame('2026-11-21', self::text($xpath, '/oai:OAI-PMH/oai:request/@until'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badDatestampProvider(): array
    {
        return [
            'relative date' => ['from=yesterday'],
            'non-existing date' => ['from=2026-02-30'],
            'missing UTC designator' => ['until=2026-11-21T23:30:00'],
            'hour out of range' => ['from=2026-11-21T24:00:00Z'],
            'timezone offset' => ['until=2026-11-21T10:00:00%2B01:00'],
            'two-digit year' => ['from=26-11-21'],
            'different granularities' => ['from=2026-11-21&until=2026-11-22T00:00:00Z'],
            'from after until' => ['from=2026-11-22&until=2026-11-21']
        ];
    }

    #[Test]
    #[DataProvider('badDatestampProvider')]
    public function itRejectsIllegalDatestamps(string $arguments): void
    {
        $this->addRecord('rec1');

        $xpath = $this->requestXml('verb=ListIdentifiers&metadataPrefix=oai_dc&' . $arguments);

        $this->assertOaiError('badArgument', $xpath);
    }

    #[Test]
    public function itReportsEmptyLists(): void
    {
        $this->addRecord('rec1', lastChanged: '2026-11-20T10:00:00Z');

        $this->assertOaiError(
            'noRecordsMatch',
            $this->requestXml('verb=ListIdentifiers&metadataPrefix=oai_dc&until=2026-11-19')
        );
        $this->assertOaiError(
            'noRecordsMatch',
            $this->requestXml('verb=ListRecords&metadataPrefix=mods')
        );
    }

    #[Test]
    public function itReportsUnsupportedFormats(): void
    {
        $this->addRecord('rec1');

        $this->assertOaiError('cannotDisseminateFormat', $this->requestXml('verb=ListRecords&metadataPrefix=marc'));
    }

    #[Test]
    public function itRejectsInvalidResumptionTokens(): void
    {
        $this->addRecord('rec1');

        $this->assertOaiError('badResumptionToken', $this->requestXml('verb=ListIdentifiers&resumptionToken=invalid'));
    }

    #[Test]
    public function itRejectsResumptionTokensOfOtherVerbs(): void
    {
        foreach (['rec1', 'rec2', 'rec3'] as $identifier) {
            $this->addRecord($identifier);
        }
        $token = self::text($this->requestXml('verb=ListIdentifiers&metadataPrefix=oai_dc'), '//oai:resumptionToken');

        $xpath = $this->requestXml('verb=ListRecords&resumptionToken=' . $token);

        $this->assertOaiError('badResumptionToken', $xpath);
    }

    #[Test]
    public function itRejectsResumptionTokensWithOtherArguments(): void
    {
        foreach (['rec1', 'rec2', 'rec3'] as $identifier) {
            $this->addRecord($identifier);
        }
        $token = self::text($this->requestXml('verb=ListIdentifiers&metadataPrefix=oai_dc'), '//oai:resumptionToken');

        $xpath = $this->requestXml('verb=ListIdentifiers&metadataPrefix=oai_dc&resumptionToken=' . $token);

        $this->assertContains('badArgument', self::errors($xpath));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badArgumentProvider(): array
    {
        return [
            'missing metadataPrefix' => ['verb=ListIdentifiers'],
            'identifier' => ['verb=ListRecords&metadataPrefix=oai_dc&identifier=rec1']
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
