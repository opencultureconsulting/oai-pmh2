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

namespace OCC\OaiPmh2\Tests\Console;

use OCC\OaiPmh2\Console;
use OCC\OaiPmh2\Console\CsvImportCommand;
use OCC\OaiPmh2\Tests\TestCase;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(CsvImportCommand::class)]
#[CoversClass(Console::class)]
final class CsvImportCommandTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        $this->useDatabase();
    }

    /**
     * Import a CSV file.
     *
     * @param list<list<string>> $rows The CSV rows (including the header)
     * @param array<string, mixed> $options Additional command options
     *
     * @return CommandTester The command tester
     */
    private function import(array $rows, array $options = []): CommandTester
    {
        $csv = '';
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(fn (string $value): string => '"' . str_replace('"', '""', $value) . '"', $row)) . "\n";
        }
        $tester = new CommandTester(new CsvImportCommand());
        $tester->execute(['format' => 'oai_dc', 'file' => $this->createTempFile($csv)] + $options);
        $this->em->clear();
        return $tester;
    }

    /**
     * Get the set specs of a record.
     *
     * @return list<string>
     */
    private function setsOf(string $identifier): array
    {
        $record = $this->em->getRecord($identifier, 'oai_dc');
        $this->assertNotNull($record, sprintf('Record "%s" does not exist.', $identifier));
        $sets = array_map('strval', $record->getSets()->getKeys());
        sort($sets);
        return $sets;
    }

    #[Test]
    public function itImportsRecordsWithDatesInUtc(): void
    {
        $tester = $this->import([
            ['identifier', 'content', 'lastChanged'],
            ['rec1', self::CONTENT, '2026-11-20T10:00:00Z'],
            ['rec2', self::CONTENT, '2026-11-21T10:00:00+01:00'],
            ['rec3', self::CONTENT, '2026-11-22 10:00:00'],
            ['rec4', self::CONTENT, '2026-11-22T10:00:00-05:00'],
            ['rec5', '', '2026-11-23T00:00:00Z']
        ]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('5 records', self::display($tester));
        $dates = [];
        foreach (['rec1', 'rec2', 'rec3', 'rec4', 'rec5'] as $identifier) {
            $dates[$identifier] = $this->em->getRecord($identifier, 'oai_dc')?->getLastChanged()->format('Y-m-d H:i:s');
        }
        $this->assertSame(
            [
                'rec1' => '2026-11-20 10:00:00',
                'rec2' => '2026-11-21 09:00:00',
                'rec3' => '2026-11-22 10:00:00',
                'rec4' => '2026-11-22 15:00:00',
                'rec5' => '2026-11-23 00:00:00'
            ],
            $dates
        );
        // Records without content are imported as deleted.
        $this->assertFalse($this->em->getRecord('rec5', 'oai_dc')?->hasContent());
    }

    #[Test]
    public function itUsesTheCurrentDateWithoutDateColumn(): void
    {
        $this->import([['identifier', 'content'], ['rec1', self::CONTENT]])->assertCommandIsSuccessful();

        $this->assertEqualsWithDelta(time(), $this->em->getRecord('rec1', 'oai_dc')?->getLastChanged()->getTimestamp(), 5);
    }

    #[Test]
    public function itCreatesSetsInBulkModeForNewAndExistingRecords(): void
    {
        $this->addRecord('rec1', sets: ['coll']);

        // Regression test: updating records with sets failed with an ORMInvalidArgumentException.
        $tester = $this->import(
            [
                ['identifier', 'content', 'sets'],
                ['rec1', self::CONTENT, 'coll,bulknew'],
                ['rec2', self::CONTENT, 'bulknew'],
                ['rec3', self::CONTENT, 'bulknew,coll']
            ],
            ['--createSets' => true]
        );

        $tester->assertCommandIsSuccessful();
        $this->assertSame(['bulknew', 'coll'], $this->setsOf('rec1'));
        $this->assertSame(['bulknew'], $this->setsOf('rec2'));
        $this->assertSame(['bulknew', 'coll'], $this->setsOf('rec3'));
    }

    #[Test]
    public function itIgnoresUnknownSetsByDefault(): void
    {
        $this->addSet('coll');

        $this->import(
            [['identifier', 'content', 'sets'], ['rec1', self::CONTENT, 'coll,unknown']],
            ['--batchSize' => '1']
        )->assertCommandIsSuccessful();

        $this->assertSame(['coll'], $this->setsOf('rec1'));
        $this->assertNull($this->em->getSet('unknown'));
    }

    #[Test]
    public function itSupportsCustomColumnNames(): void
    {
        $tester = $this->import(
            [['id', 'xml', 'date', 'collections'], ['rec1', self::CONTENT, '2026-01-01T00:00:00Z', 'coll']],
            ['--idColumn' => 'id', '--contentColumn' => 'xml', '--dateColumn' => 'date', '--setColumn' => 'collections', '--createSets' => true]
        );

        $tester->assertCommandIsSuccessful();
        $this->assertSame(['coll'], $this->setsOf('rec1'));
    }

    #[Test]
    public function itRejectsUnsupportedFormats(): void
    {
        $tester = new CommandTester(new CsvImportCommand());

        $tester->execute(['format' => 'marc', 'file' => $this->createTempFile("identifier,content\n")]);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertStringContainsString('not supported', self::display($tester));
    }

    #[Test]
    public function itRejectsFilesWithoutMandatoryColumns(): void
    {
        $tester = $this->import([['identifier', 'lastChanged'], ['rec1', '2026-01-01T00:00:00Z']]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('does not contain mandatory columns', self::display($tester));
        $this->assertFalse($this->em->isValidRecordIdentifier('rec1'));
    }

    #[Test]
    public function itRejectsEmptyFiles(): void
    {
        $tester = new CommandTester(new CsvImportCommand());

        $tester->execute(['format' => 'oai_dc', 'file' => $this->createTempFile('')]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('does not contain valid CSV', self::display($tester));
    }
}
