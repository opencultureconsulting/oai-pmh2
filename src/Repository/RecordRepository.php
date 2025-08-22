<?php

/**
 * OAI-PMH 2.0 Data Provider
 * Copyright (C) 2024 Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace OCC\OaiPmh2\Repository;

use DateTime;
use Doctrine\ORM\EntityRepository;
use OCC\OaiPmh2\Configuration;
use OCC\OaiPmh2\Entity\Record;
use OCC\OaiPmh2\EntityManager;

/**
 * Doctrine/ORM Repository for records.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 *
 * @extends EntityRepository<Record>
 */
final class RecordRepository extends EntityRepository
{
    /**
     * Add or update record.
     *
     * @param Record $entity The record
     *
     * @return void
     */
    public function addOrUpdate(Record $entity): void
    {
        /** @var EntityManager */
        $entityManager = $this->getEntityManager();
        $oldRecord = $this->find([
            'identifier' => $entity->getIdentifier(),
            'format' => $entity->getFormat()
        ]);
        if (isset($oldRecord)) {
            if ($entity->hasContent() || Configuration::getInstance()->deletedRecords !== 'no') {
                $oldRecord->setContent($entity->getContent(), false);
                $oldRecord->setLastChanged($entity->getLastChanged());
                $newSets = $entity->getSets()->toArray();
                $oldSets = $oldRecord->getSets()->toArray();
                // Add new sets.
                foreach (array_diff($newSets, $oldSets) as $newSet) {
                    $oldRecord->addSet($newSet);
                }
                // Remove old sets.
                foreach (array_diff($oldSets, $newSets) as $oldSet) {
                    $oldRecord->removeSet($oldSet);
                }
            } else {
                $entityManager->remove($oldRecord);
            }
        } else {
            if ($entity->hasContent() || Configuration::getInstance()->deletedRecords !== 'no') {
                $entityManager->persist($entity);
            }
        }
    }

    /**
     * Delete a record.
     *
     * @param Record $entity The record
     *
     * @return void
     */
    public function delete(Record $entity): void
    {
        /** @var EntityManager */
        $entityManager = $this->getEntityManager();
        if (Configuration::getInstance()->deletedRecords === 'no') {
            $entityManager->remove($entity);
            $entityManager->flush();
        } else {
            $entity->setContent();
            $entity->setLastChanged(new DateTime());
            $entityManager->flush();
        }
    }
}
