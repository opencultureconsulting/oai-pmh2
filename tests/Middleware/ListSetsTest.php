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

use OCC\OaiPmh2\Middleware\ListSets;
use OCC\OaiPmh2\Tests\TestCase;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(ListSets::class)]
final class ListSetsTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        $this->useDatabase();
    }

    #[Test]
    public function itListsAllSetsInStableOrderAcrossPages(): void
    {
        foreach (['zeta', 'alpha', 'mid', 'alpha:sub', 'beta'] as $spec) {
            $this->addSet($spec, 'Name of ' . $spec);
        }

        $specs = [];
        $cursors = [];
        $query = 'verb=ListSets';
        do {
            $xpath = $this->requestXml($query);
            $this->assertSame([], self::errors($xpath));
            array_push($specs, ...self::texts($xpath, '//oai:ListSets/oai:set/oai:setSpec'));
            $cursors[] = self::text($xpath, '//oai:resumptionToken/@cursor');
            $this->assertSame('5', self::text($xpath, '//oai:resumptionToken/@completeListSize'));
            $token = self::text($xpath, '//oai:resumptionToken');
            $query = 'verb=ListSets&resumptionToken=' . $token;
        } while ($token !== '');

        $this->assertSame(['alpha', 'alpha:sub', 'beta', 'mid', 'zeta'], $specs);
        $this->assertSame(['0', '2', '4'], $cursors);
    }

    #[Test]
    public function itListsNamesAndDescriptions(): void
    {
        $description = '<oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"'
            . ' xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:description>About</dc:description></oai_dc:dc>';
        $this->addSet('coll', 'Collection', $description);
        $this->addSet('other');

        $xpath = $this->requestXml('verb=ListSets');
        $xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');

        $this->assertSame('Collection', self::text($xpath, '//oai:set[oai:setSpec="coll"]/oai:setName'));
        $this->assertSame('About', self::text($xpath, '//oai:set[oai:setSpec="coll"]/oai:setDescription//dc:description'));
        // Without a name, the spec is used as name.
        $this->assertSame('other', self::text($xpath, '//oai:set[oai:setSpec="other"]/oai:setName'));
        $this->assertSame(0, $xpath->query('//oai:set[oai:setSpec="other"]/oai:setDescription')?->length);
    }

    #[Test]
    public function itReportsMissingSetSupport(): void
    {
        $this->assertOaiError('noSetHierarchy', $this->requestXml('verb=ListSets'));
    }

    #[Test]
    public function itRejectsIllegalArguments(): void
    {
        $this->addSet('coll');

        $this->assertOaiError('badArgument', $this->requestXml('verb=ListSets&metadataPrefix=oai_dc'));
        $this->assertOaiError('badResumptionToken', $this->requestXml('verb=ListSets&resumptionToken=invalid'));
    }
}
