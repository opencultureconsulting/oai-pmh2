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
use OCC\OaiPmh2\Entity\Set;

/**
 * Doctrine/ORM Repository for sets.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 *
 * @extends EntityRepository<Set>
 */
final class SetRepository extends EntityRepository
{
    /**
     * Add or update set.
     *
     * @param Set $entity The set
     *
     * @return void
     */
    public function addOrUpdate(Set $entity): void
    {
        $oldSet = $this->find($entity->getSpec());
        if (isset($oldSet)) {
            $oldSet->setName($entity->getName());
            $oldSet->setDescription($entity->getDescription());
        } else {
            $this->getEntityManager()->persist($entity);
        }
    }

    /**
     * Delete set.
     *
     * @param Set $entity The set
     *
     * @return void
     */
    public function delete(Set $entity): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->remove($entity);
        $entityManager->flush();
    }
}
