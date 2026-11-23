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

namespace OCC\OaiPmh2\Tests;

use DateTime;
use OCC\OaiPmh2\EntityManager;
use OCC\OaiPmh2\Entity\Record;
use OCC\OaiPmh2\Entity\Token;
use OCC\OaiPmh2\Repository\RecordRepository;
use OCC\OaiPmh2\Repository\SetRepository;
use OCC\OaiPmh2\ResultSet;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;

#[CoversClass(EntityManager::class)]
#[CoversClass(RecordRepository::class)]
#[CoversClass(SetRepository::class)]
#[CoversClass(ResultSet::class)]
final class EntityManagerTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        $this->useDatabase();
    }

    /**
     * Get the identifiers of all stored records (including deleted ones).
     *
     * @return list<string>
     */
    private function storedIdentifiers(string $format = 'oai_dc'): array
    {
        $this->em->clear();
        $identifiers = [];
        foreach ($this->em->getRepository(Record::class)->findBy(['metadataPrefix' => $format]) as $record) {
            $identifiers[] = $record->getIdentifier();
        }
        sort($identifiers);
        return $identifiers;
    }

    #[Test]
    public function itReturnsTheEarliestDatestamp(): void
    {
        $this->assertSame('0000-01-01T00:00:00Z', $this->em->getEarliestDatestamp());

        $this->addRecord('rec1', lastChanged: '2026-05-01T10:00:00Z');
        $this->addRecord('rec2', lastChanged: '2026-04-01T10:00:00Z', format: 'mods');

        $this->assertSame('2026-04-01T10:00:00Z', $this->em->getEarliestDatestamp());
    }

    #[Test]
    public function itReturnsNullForUnknownFormatsAndSets(): void
    {
        $this->addSet('coll');

        $this->assertNotNull($this->em->getMetadataFormat('oai_dc'));
        $this->assertNull($this->em->getMetadataFormat('marc'));
        $this->assertNotNull($this->em->getSet('coll'));
        $this->assertNull($this->em->getSet('other'));
    }

    #[Test]
    public function itReturnsRecords(): void
    {
        $this->addRecord('rec1');

        $this->assertSame('rec1', $this->em->getRecord('rec1', 'oai_dc')?->getIdentifier());
        $this->assertNull($this->em->getRecord('rec1', 'mods'));
        $this->assertNull($this->em->getRecord('rec1', 'marc'));
        $this->assertTrue($this->em->isValidRecordIdentifier('rec1'));
        $this->assertFalse($this->em->isValidRecordIdentifier('rec2'));
    }

    #[Test]
    public function itIndexesResultsByIdentifierAndSpec(): void
    {
        $this->addRecord('rec-b', sets: ['coll-b']);
        $this->addRecord('rec-a', sets: ['coll-a']);

        $this->assertSame(['rec-a', 'rec-b'], $this->em->getRecords('ListIdentifiers', 'oai_dc')->getKeys());
        $this->assertSame(['coll-a', 'coll-b'], $this->em->getSets()->getKeys());
        $this->assertSame(['oai_dc'], $this->em->getMetadataFormats('rec-a')->getKeys());
    }

    #[Test]
    public function itCreatesResumptionTokensForIncompleteLists(): void
    {
        foreach (['rec1', 'rec2', 'rec3'] as $identifier) {
            $this->addRecord($identifier, sets: ['coll']);
        }

        $first = $this->em->getRecords('ListRecords', 'oai_dc', 0, '2026-01-01T00:00:00Z', null, 'coll');
        $second = $this->em->getRecords('ListRecords', 'oai_dc', 1, '2026-01-01T00:00:00Z', null, 'coll');

        $token = $first->getResumptionToken();
        $this->assertNotNull($token);
        $this->assertSame(
            [
                'verb' => 'ListRecords',
                'metadataPrefix' => 'oai_dc',
                'from' => '2026-01-01T00:00:00Z',
                'set' => 'coll',
                'counter' => 1,
                'completeListSize' => 3
            ],
            $token->getParameters()
        );
        $this->assertSame(['rec3'], $second->getKeys());
        $this->assertNull($second->getResumptionToken());
    }

    #[Test]
    public function itOnlyAcceptsValidResumptionTokens(): void
    {
        $token = new Token('ListSets', ['verb' => 'ListSets', 'counter' => 1, 'completeListSize' => 3]);
        $this->em->persist($token);
        $this->em->flush();

        $this->assertSame($token, $this->em->getResumptionToken($token->getToken(), 'ListSets'));
        $this->assertNull($this->em->getResumptionToken($token->getToken(), 'ListRecords'));
        $this->assertNull($this->em->getResumptionToken('invalid', 'ListSets'));

        (new ReflectionProperty(Token::class, 'validUntil'))->setValue($token, new DateTime('-1 second'));
        $this->em->flush();

        $this->assertNull($this->em->getResumptionToken($token->getToken(), 'ListSets'));
        // Expired tokens are deleted.
        $this->em->clear();
        $this->assertNull($this->em->getRepository(Token::class)->find($token->getToken()));
    }

    #[Test]
    public function itPrunesExpiredTokens(): void
    {
        $expired = new Token('ListSets', ['verb' => 'ListSets', 'counter' => 1, 'completeListSize' => 3]);
        (new ReflectionProperty(Token::class, 'validUntil'))->setValue($expired, new DateTime('-1 minute'));
        $valid = new Token('ListSets', ['verb' => 'ListSets', 'counter' => 1, 'completeListSize' => 3]);
        // Make sure both tokens differ even if created in the same microsecond.
        (new ReflectionProperty(Token::class, 'token'))->setValue($valid, 'abcdef01');
        $this->em->persist($expired);
        $this->em->persist($valid);
        $this->em->flush();

        $this->assertSame(1, $this->em->pruneExpiredTokens());
        $this->em->clear();
        $this->assertNotNull($this->em->getRepository(Token::class)->find('abcdef01'));
    }

    #[Test]
    public function itPurgesRecordsByMarkingThemAsDeleted(): void
    {
        $this->addRecord('rec1', lastChanged: '2026-01-01T00:00:00Z');
        $this->addRecord('rec2', format: 'mods');

        $this->assertSame(1, $this->em->purgeRecords('oai_dc'));

        $this->em->clear();
        $record = $this->em->getRecord('rec1', 'oai_dc');
        $this->assertNotNull($record);
        $this->assertFalse($record->hasContent());
        $this->assertGreaterThan(new DateTime('2026-01-02'), $record->getLastChanged());
        $this->assertTrue($this->em->getRecord('rec2', 'mods')?->hasContent());
    }

    #[Test]
    public function itPurgesRecordsCompletelyWithoutDeletedRecordSupport(): void
    {
        $this->useConfiguration(['deletedRecords' => 'no']);
        $this->addRecord('rec1');
        $this->addRecord('rec2', format: 'mods');

        $this->assertSame(1, $this->em->purgeRecords('oai_dc'));

        $this->assertSame([], $this->storedIdentifiers());
        $this->assertSame(['rec2'], $this->storedIdentifiers('mods'));
    }

    #[Test]
    public function itPrunesDeletedRecords(): void
    {
        $this->addRecord('rec1');
        $this->addRecord('rec2', content: null);

        $this->assertSame(1, $this->em->pruneDeletedRecords());

        $this->assertSame(['rec1'], $this->storedIdentifiers());
    }

    #[Test]
    public function itPrunesOrphanedSets(): void
    {
        $this->addSet('empty');
        $this->addRecord('rec1', sets: ['used']);

        $this->assertSame(1, $this->em->pruneOrphanedSets());

        $this->assertSame(['used'], $this->em->getSets()->getKeys());
    }

    #[Test]
    public function itUpdatesRecordsAndTheirSets(): void
    {
        $this->addRecord('rec1', sets: ['a', 'b']);

        // Regression test: updating a record with sets failed with an ORMInvalidArgumentException.
        $this->addRecord('rec1', '<new xmlns="urn:x"/>', '2026-06-01T00:00:00Z', ['b', 'c']);

        $this->em->clear();
        $record = $this->em->getRecord('rec1', 'oai_dc');
        $this->assertNotNull($record);
        $this->assertSame('<new xmlns="urn:x"/>', $record->getContent());
        $this->assertSame('2026-06-01T00:00:00Z', $record->getLastChanged()->format('Y-m-d\TH:i:s\Z'));
        $this->assertEqualsCanonicalizing(['b', 'c'], $record->getSets()->getKeys());
    }

    #[Test]
    public function itDeletesRecordsAccordingToThePolicy(): void
    {
        $this->addRecord('rec1', sets: ['coll']);
        $this->addRecord('rec2', sets: ['coll']);

        // With deleted record support, records are only marked as deleted.
        $this->addRecord('rec1', content: null, sets: ['coll']);
        $this->assertSame(['rec1', 'rec2'], $this->storedIdentifiers());
        $this->assertFalse($this->em->getRecord('rec1', 'oai_dc')?->hasContent());

        // Without, they are removed completely.
        $this->useConfiguration(['deletedRecords' => 'no']);
        $this->addRecord('rec2', content: null, sets: ['coll']);
        $this->assertSame(['rec1'], $this->storedIdentifiers());

        // New deleted records are not even added.
        $this->addRecord('rec3', content: null, sets: ['coll']);
        $this->assertSame(['rec1'], $this->storedIdentifiers());
    }
}
