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

use OCC\OaiPmh2\Console\AddSetCommand;
use OCC\OaiPmh2\Console\DeleteSetCommand;
use OCC\OaiPmh2\Console\PruneOrphanedSetsCommand;
use OCC\OaiPmh2\Tests\TestCase;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(AddSetCommand::class)]
#[CoversClass(DeleteSetCommand::class)]
#[CoversClass(PruneOrphanedSetsCommand::class)]
final class SetCommandsTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        $this->useDatabase();
    }

    #[Test]
    public function itAddsSets(): void
    {
        $file = $this->createTempFile('<description xmlns="urn:x">About</description>');
        $tester = new CommandTester(new AddSetCommand());

        $tester->execute(['setSpec' => 'coll', 'setName' => 'Collection', 'file' => $file]);
        $tester->execute(['setSpec' => 'other']);

        $tester->assertCommandIsSuccessful();
        $this->em->clear();
        $this->assertSame('Collection', $this->em->getSet('coll')?->getName());
        $this->assertSame('<description xmlns="urn:x">About</description>', $this->em->getSet('coll')?->getDescription());
        $this->assertSame('other', $this->em->getSet('other')?->getName());
    }

    #[Test]
    public function itUpdatesSets(): void
    {
        $this->addSet('coll', 'Old Name', '<old xmlns="urn:x"/>');
        $tester = new CommandTester(new AddSetCommand());

        $tester->execute(['setSpec' => 'coll', 'setName' => 'New Name']);

        $tester->assertCommandIsSuccessful();
        $this->em->clear();
        $this->assertSame('New Name', $this->em->getSet('coll')?->getName());
        $this->assertNull($this->em->getSet('coll')?->getDescription());
    }

    #[Test]
    public function newSetsAreListedImmediately(): void
    {
        $this->addSet('first');
        $this->assertSame(['first'], self::texts($this->requestXml('verb=ListSets'), '//oai:setSpec'));

        (new CommandTester(new AddSetCommand()))->execute(['setSpec' => 'second']);

        // The result cache must be cleared.
        $this->assertSame(['first', 'second'], self::texts($this->requestXml('verb=ListSets'), '//oai:setSpec'));
    }

    #[Test]
    public function itRejectsMissingDescriptionFiles(): void
    {
        $tester = new CommandTester(new AddSetCommand());

        $tester->execute(['setSpec' => 'coll', 'file' => sys_get_temp_dir() . '/oai-pmh2-test-does-not-exist.xml']);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertNull($this->em->getSet('coll'));
    }

    #[Test]
    public function itDeletesSets(): void
    {
        $this->addRecord('rec1', sets: ['coll', 'other']);
        $tester = new CommandTester(new DeleteSetCommand());

        $tester->execute(['setSpec' => 'coll']);

        $tester->assertCommandIsSuccessful();
        $this->em->clear();
        $this->assertNull($this->em->getSet('coll'));
        $this->assertSame(['other'], $this->em->getRecord('rec1', 'oai_dc')?->getSets()->getKeys());
    }

    #[Test]
    public function itReportsUnknownSetsWhenDeleting(): void
    {
        $tester = new CommandTester(new DeleteSetCommand());

        $tester->execute(['setSpec' => 'coll']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('not found', self::display($tester));
    }

    #[Test]
    public function itPrunesOrphanedSets(): void
    {
        $this->addSet('empty');
        $this->addRecord('rec1', sets: ['used']);
        $this->assertSame(['empty', 'used'], self::texts($this->requestXml('verb=ListSets'), '//oai:setSpec'));
        $tester = new CommandTester(new PruneOrphanedSetsCommand());

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('1 orphaned sets', self::display($tester));
        $this->assertSame(['used'], self::texts($this->requestXml('verb=ListSets'), '//oai:setSpec'));
    }
}
