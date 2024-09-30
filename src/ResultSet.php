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

namespace OCC\OaiPmh2;

use Doctrine\Common\Collections\ArrayCollection;
use OCC\OaiPmh2\Entity\Token;

/**
 * A database result set with optional resumption token.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 *
 * @template TEntity of Entity
 * @extends ArrayCollection<string, TEntity>
 */
final class ResultSet extends ArrayCollection
{
    /**
     * This holds the optional resumption token.
     */
    private ?Token $resumptionToken;

    /**
     * Get the resumption token.
     *
     * @return ?Token The resumption token or NULL if not applicable
     */
    public function getResumptionToken(): ?Token
    {
        return $this->resumptionToken;
    }

    /**
     * Set the resumption token.
     *
     * @param Token $token The resumption token
     *
     * @return void
     */
    public function setResumptionToken(Token $token): void
    {
        $this->resumptionToken = $token;
    }

    /**
     * Create new result set.
     *
     * @param array<string, TEntity> $elements Array of entities
     * @param Token $token Optional resumption token
     */
    public function __construct(array $elements = [], Token $token = null)
    {
        parent::__construct(elements: $elements);
        $this->resumptionToken = $token;
    }
}
