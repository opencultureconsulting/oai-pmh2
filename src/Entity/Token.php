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

namespace OCC\OaiPmh2\Entity;

use DateInterval;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use OCC\OaiPmh2\Configuration;
use OCC\OaiPmh2\Entity;
use OCC\OaiPmh2\Repository\TokenRepository;

/**
 * Doctrine/ORM Entity for resumption tokens.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 *
 * @phpstan-type TokenParameters = array{
 *     verb: 'ListIdentifiers'|'ListRecords'|'ListSets',
 *     metadataPrefix?: non-empty-string,
 *     from?: non-empty-string,
 *     until?: non-empty-string,
 *     set?: non-empty-string,
 *     counter: non-negative-int,
 *     completeListSize: non-negative-int
 * }
 */
#[ORM\Entity(repositoryClass: TokenRepository::class)]
#[ORM\Table(name: 'tokens')]
#[ORM\Index(name: 'valid_until_idx', columns: ['valid_until'])]
class Token extends Entity
{
    /**
     * The resumption token.
     */
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 8)]
    private string $token;

    /**
     * The verb for which the token is issued.
     */
    #[ORM\Column(type: 'string', length: 16)]
    private string $verb;

    /**
     * The request parameters.
     *
     * @var TokenParameters
     */
    #[ORM\Column(type: 'json')]
    private array $parameters;

    /**
     * The date and time of validity.
     */
    #[ORM\Column(name: 'valid_until', type: 'datetime')]
    private DateTime $validUntil;

    /**
     * Get the resumption token.
     *
     * @return string The resumption token
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * Get the request parameters.
     *
     * @return TokenParameters The request parameters
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Get the date and time of validity.
     *
     * @return DateTime The datetime of validity
     */
    public function getValidUntil(): DateTime
    {
        return $this->validUntil;
    }

    /**
     * Get the verb for which the token was issued.
     *
     * @return string The verb
     */
    public function getVerb(): string
    {
        return $this->verb;
    }

    /**
     * Get new entity of resumption token.
     *
     * @param string $verb The verb for which the token is issued
     * @param TokenParameters $parameters The request parameters
     */
    public function __construct(string $verb, array $parameters)
    {
        $this->token = substr(md5(microtime()), 0, 8);
        $this->verb = $verb;
        $this->parameters = $parameters;
        $validUntil = new DateTime();
        $validUntil->add(new DateInterval('PT' . Configuration::getInstance()->tokenValid . 'S'));
        $this->validUntil = $validUntil;
    }
}
