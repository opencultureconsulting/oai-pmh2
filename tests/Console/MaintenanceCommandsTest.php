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

use DateTime;
use OCC\OaiPmh2\Console\PruneDeletedRecordsCommand;
use OCC\OaiPmh2\Console\PruneResumptionTokensCommand;
use OCC\OaiPmh2\Console\UpdateFormatsCommand;
use OCC\OaiPmh2\Entity\Token;
use OCC\OaiPmh2\Tests\TestCase;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(PruneDeletedRecordsCommand::class)]
#[CoversClass(PruneResumptionTokensCommand::class)]
#[CoversClass(UpdateFormatsCommand::class)]
final class MaintenanceCommandsTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        $this->useDatabase();
    }

    #[Test]
    public function itPrunesDeletedRecordsDependingOnThePolicy(): void
    {
        $this->addRecord('rec1');
        $this->addRecord('rec2', content: null);
        $tester = new CommandTester(new PruneDeletedRecordsCommand());

        $this->useConfiguration(['deletedRecords' => 'persistent']);
        $tester->execute([]);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());

        $this->useConfiguration(['deletedRecords' => 'transient']);
        $tester->execute([]);
        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertTrue($this->em->isValidRecordIdentifier('rec2'));

        $tester->execute(['--force' => true]);
        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('1 deleted records', self::display($tester));
        $this->em->clear();
        $this->assertFalse($this->em->isValidRecordIdentifier('rec2'));
        $this->assertTrue($this->em->isValidRecordIdentifier('rec1'));
    }

    #[Test]
    public function itPrunesExpiredTokens(): void
    {
        $token = new Token('ListSets', ['verb' => 'ListSets', 'counter' => 1, 'completeListSize' => 3]);
        (new ReflectionProperty(Token::class, 'validUntil'))->setValue($token, new DateTime('-1 minute'));
        $this->em->persist($token);
        $this->em->flush();
        $tester = new CommandTester(new PruneResumptionTokensCommand());

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('1 expired resumption tokens', self::display($tester));
    }

    #[Test]
    public function itAddsAndUpdatesFormatsFromTheConfiguration(): void
    {
        $this->useConfiguration(['metadataPrefix' => [
            'oai_dc' => [
                'namespace' => 'http://www.openarchives.org/OAI/2.0/oai_dc/',
                'schema' => 'https://example.org/new/oai_dc.xsd'
            ],
            'mods' => self::SETTINGS['metadataPrefix']['mods'],
            'marc' => [
                'namespace' => 'http://www.loc.gov/MARC21/slim',
                'schema' => 'https://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd'
            ]
        ]]);
        $tester = new CommandTester(new UpdateFormatsCommand());

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->em->clear();
        $this->assertEqualsCanonicalizing(['oai_dc', 'mods', 'marc'], $this->em->getMetadataFormats()->getKeys());
        $this->assertSame('https://example.org/new/oai_dc.xsd', $this->em->getMetadataFormat('oai_dc')?->getSchema());
    }

    #[Test]
    public function itDeletesRemovedFormatsAfterConfirmation(): void
    {
        $this->addRecord('rec1', format: 'mods', sets: ['coll']);
        $this->useConfiguration(['metadataPrefix' => ['oai_dc' => self::SETTINGS['metadataPrefix']['oai_dc']]]);
        $tester = new CommandTester(new UpdateFormatsCommand());

        $tester->setInputs(['no']);
        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->em->clear();
        $this->assertNotNull($this->em->getMetadataFormat('mods'));

        $tester->setInputs(['yes']);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->em->clear();
        $this->assertNull($this->em->getMetadataFormat('mods'));
        $this->assertFalse($this->em->isValidRecordIdentifier('rec1'));
    }

    #[Test]
    public function itOnlyListsFormatsOnRequest(): void
    {
        $this->useConfiguration(['metadataPrefix' => []]);
        $tester = new CommandTester(new UpdateFormatsCommand());

        $tester->execute(['--list' => true]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('"oai_dc", "mods"', self::display($tester));
        $this->assertCount(2, $this->em->getMetadataFormats());
    }
}
