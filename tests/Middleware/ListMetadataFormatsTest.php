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

use OCC\OaiPmh2\Middleware\ListMetadataFormats;
use OCC\OaiPmh2\Tests\TestCase;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(ListMetadataFormats::class)]
final class ListMetadataFormatsTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        $this->useDatabase();
    }

    #[Test]
    public function itListsAllFormats(): void
    {
        $xpath = $this->requestXml('verb=ListMetadataFormats');

        $this->assertSame([], self::errors($xpath));
        $format = '//oai:ListMetadataFormats/oai:metadataFormat';
        $this->assertEqualsCanonicalizing(['oai_dc', 'mods'], self::texts($xpath, $format . '/oai:metadataPrefix'));
        $this->assertSame(
            'https://www.openarchives.org/OAI/2.0/oai_dc.xsd',
            self::text($xpath, $format . '[oai:metadataPrefix="oai_dc"]/oai:schema')
        );
        $this->assertSame(
            'http://www.openarchives.org/OAI/2.0/oai_dc/',
            self::text($xpath, $format . '[oai:metadataPrefix="oai_dc"]/oai:metadataNamespace')
        );
    }

    #[Test]
    public function itListsTheFormatsOfARecord(): void
    {
        $this->addRecord('rec1', format: 'mods');
        $this->addRecord('rec2');

        $xpath = $this->requestXml('verb=ListMetadataFormats&identifier=rec1');

        $this->assertSame(['mods'], self::texts($xpath, '//oai:metadataFormat/oai:metadataPrefix'));
    }

    #[Test]
    public function itReportsUnknownIdentifiers(): void
    {
        $this->assertOaiError('idDoesNotExist', $this->requestXml('verb=ListMetadataFormats&identifier=rec1'));
    }

    #[Test]
    public function itReportsMissingFormats(): void
    {
        $this->useConfiguration(['metadataPrefix' => []]);
        $this->useDatabase();

        $this->assertOaiError('noMetadataFormats', $this->requestXml('verb=ListMetadataFormats'));
    }

    #[Test]
    public function itRejectsIllegalArguments(): void
    {
        $this->assertOaiError('badArgument', $this->requestXml('verb=ListMetadataFormats&metadataPrefix=oai_dc'));
        $this->assertOaiError(
            'badArgument',
            $this->requestXml('verb=ListMetadataFormats&identifier=rec1&set=coll')
        );
    }
}
