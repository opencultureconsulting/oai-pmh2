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
use OCC\OaiPmh2\Console\AddRecordCommand;
use OCC\OaiPmh2\Console\DeleteRecordCommand;
use OCC\OaiPmh2\Tests\TestCase;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(AddRecordCommand::class)]
#[CoversClass(DeleteRecordCommand::class)]
#[CoversClass(Console::class)]
final class RecordCommandsTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        $this->useDatabase();
    }

    /**
     * Run the "oai:add:record" command.
     *
     * @param array<string, mixed> $input The command input
     *
     * @return CommandTester The command tester
     */
    private function addRecordCommand(array $input): CommandTester
    {
        $tester = new CommandTester(new AddRecordCommand());
        $tester->execute($input);
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
    public function itAddsRecordsToExistingSets(): void
    {
        $this->addSet('coll');
        $file = $this->createTempFile(self::CONTENT);

        $tester = $this->addRecordCommand([
            'identifier' => 'rec1',
            'format' => 'oai_dc',
            'file' => $file,
            'sets' => ['coll', 'unknown']
        ]);

        $tester->assertCommandIsSuccessful();
        $this->assertSame(self::CONTENT, $this->em->getRecord('rec1', 'oai_dc')?->getContent());
        // Unknown sets are ignored without "--createSets".
        $this->assertSame(['coll'], $this->setsOf('rec1'));
        $this->assertNull($this->em->getSet('unknown'));
    }

    #[Test]
    public function itCreatesUnknownSetsOnRequest(): void
    {
        $file = $this->createTempFile(self::CONTENT);

        $tester = $this->addRecordCommand([
            'identifier' => 'rec1',
            'format' => 'oai_dc',
            'file' => $file,
            'sets' => ['coll:new'],
            '--createSets' => true
        ]);

        $tester->assertCommandIsSuccessful();
        $this->assertSame(['coll:new'], $this->setsOf('rec1'));
    }

    #[Test]
    public function itUpdatesRecordsWithSets(): void
    {
        $this->addRecord('rec1', sets: ['old', 'kept']);
        $file = $this->createTempFile('<updated xmlns="urn:x"/>');

        // Regression test: updating a record with sets failed with an ORMInvalidArgumentException.
        $tester = $this->addRecordCommand([
            'identifier' => 'rec1',
            'format' => 'oai_dc',
            'file' => $file,
            'sets' => ['kept', 'new'],
            '--createSets' => true
        ]);

        $tester->assertCommandIsSuccessful();
        $this->assertSame('<updated xmlns="urn:x"/>', $this->em->getRecord('rec1', 'oai_dc')?->getContent());
        $this->assertSame(['kept', 'new'], $this->setsOf('rec1'));
    }

    #[Test]
    public function itMarksRecordsWithEmptyFilesAsDeleted(): void
    {
        $this->addRecord('rec1');
        $file = $this->createTempFile(" \n");

        $this->addRecordCommand(['identifier' => 'rec1', 'format' => 'oai_dc', 'file' => $file])
            ->assertCommandIsSuccessful();

        $this->assertFalse($this->em->getRecord('rec1', 'oai_dc')?->hasContent());
    }

    #[Test]
    public function itRejectsUnknownFormats(): void
    {
        $file = $this->createTempFile(self::CONTENT);

        $tester = $this->addRecordCommand(['identifier' => 'rec1', 'format' => 'marc', 'file' => $file]);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertStringContainsString('Metadata format "marc" is not supported.', self::display($tester));
        $this->assertStringNotContainsString('successfully', self::display($tester));
        $this->assertFalse($this->em->isValidRecordIdentifier('rec1'));
    }

    #[Test]
    public function itRejectsMissingFiles(): void
    {
        $tester = $this->addRecordCommand([
            'identifier' => 'rec1',
            'format' => 'oai_dc',
            'file' => sys_get_temp_dir() . '/oai-pmh2-test-does-not-exist.xml'
        ]);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertFalse($this->em->isValidRecordIdentifier('rec1'));
    }

    #[Test]
    public function itMarksDeletedRecords(): void
    {
        $this->addRecord('rec1');
        $tester = new CommandTester(new DeleteRecordCommand());

        $tester->execute(['identifier' => 'rec1', 'format' => 'oai_dc']);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('marked as deleted', self::display($tester));
        $this->em->clear();
        $this->assertFalse($this->em->getRecord('rec1', 'oai_dc')?->hasContent());
    }

    #[Test]
    public function itRemovesDeletedRecordsWithoutDeletedRecordSupport(): void
    {
        $this->useConfiguration(['deletedRecords' => 'no']);
        $this->addRecord('rec1', sets: ['coll']);
        $tester = new CommandTester(new DeleteRecordCommand());

        $tester->execute(['identifier' => 'rec1', 'format' => 'oai_dc']);

        $tester->assertCommandIsSuccessful();
        $this->em->clear();
        $this->assertFalse($this->em->isValidRecordIdentifier('rec1'));
    }

    #[Test]
    public function itReportsUnknownRecordsWhenDeleting(): void
    {
        $tester = new CommandTester(new DeleteRecordCommand());

        $tester->execute(['identifier' => 'rec1', 'format' => 'oai_dc']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('not found', self::display($tester));
    }
}
