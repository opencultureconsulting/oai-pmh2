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

use Countable;
use Iterator;
use OCC\Basics\InterfaceTraits\Countable as CountableTrait;
use OCC\Basics\InterfaceTraits\Iterator as IteratorTrait;
use OCC\OaiPmh2\Entity\Format;
use OCC\OaiPmh2\Entity\Record;
use OCC\OaiPmh2\Entity\Set;
use OCC\OaiPmh2\Entity\Token;

/**
 * A database result set with optional resumption token.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 *
 * @template QueryResult of array<string, Format|Record|Set>
 * @implements Iterator<QueryResult>
 */
class Result implements Countable, Iterator
{
    use CountableTrait;
    use IteratorTrait;

    /**
     * This holds the Doctrine result set.
     *
     * @var QueryResult
     */
    private array $data = [];

    /**
     * This holds the optional resumption token.
     */
    protected ?Token $resumptionToken;

    /**
     * Get the query result.
     *
     * @return QueryResult The result set
     */
    public function getQueryResult(): array
    {
        return $this->data;
    }

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
     * @param QueryResult $queryResult The Doctrine result set
     */
    public function __construct(array $queryResult)
    {
        $this->data = $queryResult;
    }
}
