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

namespace OCC\OaiPmh2\Tests\Entity;

use DateTime;
use DateTimeZone;
use OCC\OaiPmh2\Entity;
use OCC\OaiPmh2\Entity\Format;
use OCC\OaiPmh2\Entity\Record;
use OCC\OaiPmh2\Entity\Set;
use OCC\OaiPmh2\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[CoversClass(Record::class)]
#[CoversClass(Entity::class)]
final class RecordTest extends TestCase
{
    private static function format(): Format
    {
        return new Format('oai_dc', 'http://www.openarchives.org/OAI/2.0/oai_dc/', 'https://www.openarchives.org/OAI/2.0/oai_dc.xsd');
    }

    #[Test]
    public function itHoldsItsData(): void
    {
        $format = self::format();
        $record = new Record('oai:example.org:1', $format, '  ' . self::CONTENT . "\n", new DateTime('2026-01-02T03:04:05Z'));

        $this->assertSame('oai:example.org:1', $record->getIdentifier());
        $this->assertSame($format, $record->getFormat());
        $this->assertTrue($record->hasContent());
        $this->assertSame(self::CONTENT, $record->getContent());
        $this->assertSame(self::CONTENT, (string) $record);
        $this->assertSame('2026-01-02T03:04:05Z', $record->getLastChanged()->format('Y-m-d\TH:i:s\Z'));
    }

    #[Test]
    public function itCanBeDeleted(): void
    {
        $record = new Record('rec1', self::format());

        $this->assertFalse($record->hasContent());
        $this->assertNull($record->getContent());
        $this->assertSame('', (string) $record);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidIdentifierProvider(): array
    {
        return [
            'empty' => [''],
            'whitespace' => ['rec 1'],
            'invalid character' => ['rec<1>']
        ];
    }

    #[Test]
    #[DataProvider('invalidIdentifierProvider')]
    public function itRejectsInvalidIdentifiers(string $identifier): void
    {
        $this->expectException(ValidationFailedException::class);

        new Record($identifier, self::format());
    }

    #[Test]
    public function itRejectsInvalidContent(): void
    {
        $this->expectException(ValidationFailedException::class);

        new Record('rec1', self::format(), '<not>well-formed');
    }

    #[Test]
    public function itCanSkipContentValidation(): void
    {
        $record = new Record('rec1', self::format());
        $record->setContent(' <not>well-formed ', false);

        $this->assertSame('<not>well-formed', $record->getContent());
    }

    #[Test]
    public function itStoresTheDateOfLastChangeInUtc(): void
    {
        $lastChanged = new DateTime('2026-11-21T10:00:00+01:00');
        $record = new Record('rec1', self::format(), null, $lastChanged);

        $this->assertSame('2026-11-21 09:00:00', $record->getLastChanged()->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $record->getLastChanged()->getTimezone()->getName());
        // The given object must not be changed.
        $this->assertSame('+01:00', $lastChanged->getTimezone()->getName());
    }

    #[Test]
    public function itUsesTheCurrentDateByDefault(): void
    {
        $before = new DateTime();
        $record = new Record('rec1', self::format());
        $after = new DateTime();

        $this->assertGreaterThanOrEqual($before->getTimestamp(), $record->getLastChanged()->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $record->getLastChanged()->getTimestamp());
        $this->assertSame('UTC', $record->getLastChanged()->getTimezone()->getName());
    }

    #[Test]
    public function itConvertsTheCurrentDateToUtc(): void
    {
        $timezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Berlin');
        try {
            $record = new Record('rec1', self::format());
        } finally {
            date_default_timezone_set($timezone);
        }

        $this->assertSame('UTC', $record->getLastChanged()->getTimezone()->getName());
        $this->assertEqualsWithDelta(time(), $record->getLastChanged()->getTimestamp(), 5);
    }

    #[Test]
    public function itManagesSetsOnBothSides(): void
    {
        $record = new Record('rec1', self::format());
        $set = new Set('coll');

        $record->addSet($set);
        $record->addSet($set);

        $this->assertSame([$set], array_values($record->getSets()->toArray()));
        $this->assertTrue($set->getRecords()->contains($record));
        $this->assertCount(1, $set->getRecords());

        $set->removeRecord($record);

        $this->assertCount(0, $record->getSets());
        $this->assertTrue($set->isEmpty());
    }

    #[Test]
    public function itKeepsTheTimezoneOfOtherDates(): void
    {
        $record = new Record('rec1', self::format());
        $lastChanged = new DateTime('2026-06-01 12:00:00', new DateTimeZone('America/New_York'));
        $record->setLastChanged($lastChanged);

        $this->assertSame('2026-06-01 16:00:00', $record->getLastChanged()->format('Y-m-d H:i:s'));
        $this->assertSame('America/New_York', $lastChanged->getTimezone()->getName());
    }
}
