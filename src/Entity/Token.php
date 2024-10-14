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
 * @psalm-import-type OaiRequestMetadata from \OCC\OaiPmh2\Middleware
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
    #[ORM\Column(type: 'string')]
    private string $token;

    /**
     * The verb for which the token is issued.
     */
    #[ORM\Column(type: 'string')]
    private string $verb;

    /**
     * The query parameters as serialized array.
     */
    #[ORM\Column(type: 'string')]
    private string $parameters;

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
     * Get the query parameters.
     *
     * @return OaiRequestMetadata The query parameters
     */
    public function getParameters(): array
    {
        /** @var OaiRequestMetadata */
        return unserialize($this->parameters);
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
     * @param OaiRequestMetadata $parameters The query parameters
     */
    public function __construct(string $verb, array $parameters)
    {
        $this->token = substr(md5(microtime()), 0, 8);
        $this->verb = $verb;
        $this->parameters = serialize($parameters);
        $validUntil = new DateTime();
        $validUntil->add(new DateInterval('PT' . Configuration::getInstance()->tokenValid . 'S'));
        $this->validUntil = $validUntil;
    }
}
