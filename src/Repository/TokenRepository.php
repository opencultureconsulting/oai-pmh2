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
use OCC\OaiPmh2\Entity\Token;

/**
 * Doctrine/ORM Repository for resumption tokens.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 *
 * @extends EntityRepository<Token>
 */
final class TokenRepository extends EntityRepository
{
    /**
     * Add resumption token.
     *
     * @param Token $entity The resumption token
     *
     * @return void
     */
    public function addOrUpdate(Token $entity): void
    {
        $this->getEntityManager()->persist($entity);
    }

    /**
     * Delete resumption token.
     *
     * @param Token $entity The resumption token
     *
     * @return void
     */
    public function delete(Token $entity): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->remove($entity);
        $entityManager->flush();
    }
}
