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

use Doctrine\ORM\EntityRepository;
use OCC\OaiPmh2\Entity\Format;
use OCC\OaiPmh2\EntityManager;

/**
 * Doctrine/ORM Repository for formats.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 *
 * @extends EntityRepository<Format>
 */
final class FormatRepository extends EntityRepository
{
    /**
     * Add or update metadata format.
     *
     * @param Format $entity The metadata format
     *
     * @return void
     */
    public function addOrUpdate(Format $entity): void
    {
        $oldFormat = $this->find(id: $entity->getPrefix());
        if (isset($oldFormat)) {
            $oldFormat->setNamespace(namespace: $entity->getNamespace());
            $oldFormat->setSchema(schema: $entity->getSchema());
        } else {
            $this->getEntityManager()->persist(object: $entity);
        }
    }

    /**
     * Delete metadata format and all associated records.
     *
     * @param Format $entity The metadata format
     *
     * @return void
     */
    public function delete(Format $entity): void
    {
        /** @var EntityManager */
        $entityManager = $this->getEntityManager();
        $entityManager->remove(object: $entity);
        $entityManager->flush();
        $entityManager->pruneOrphanedSets();
    }
}
