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

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use OCC\OaiPmh2\Entity;
use OCC\OaiPmh2\Repository\FormatRepository;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Doctrine/ORM Entity for formats.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 */
#[ORM\Entity(repositoryClass: FormatRepository::class)]
#[ORM\Table(name: 'formats')]
final class Format extends Entity
{
    /**
     * The unique metadata prefix.
     */
    #[ORM\Id]
    #[ORM\Column(type: 'string')]
    private string $prefix;

    /**
     * The format's namespace URI.
     */
    #[ORM\Column(type: 'string')]
    private string $namespace;

    /**
     * The format's schema URL.
     */
    #[ORM\Column(type: 'string')]
    private string $xmlSchema;

    /**
     * The format's associated records.
     *
     * @var Collection<string, Record>
     */
    #[ORM\OneToMany(
        targetEntity: Record::class,
        mappedBy: 'format',
        fetch: 'EXTRA_LAZY',
        orphanRemoval: true,
        indexBy: 'identifier'
    )]
    private Collection $records;

    /**
     * Get the namespace URI for this format.
     *
     * @return string The namespace URI
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * Get the metadata prefix for this format.
     *
     * @return string The metadata prefix
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Get the associated records for this format.
     *
     * @return Collection<string, Record> The collection of records
     */
    public function getRecords(): Collection
    {
        return $this->records;
    }

    /**
     * Get the schema URL for this format.
     *
     * @return string The schema URL
     */
    public function getSchema(): string
    {
        return $this->xmlSchema;
    }

    /**
     * Set the namespace URI for this format.
     *
     * @param string $namespace The namespace URI
     *
     * @return void
     *
     * @throws ValidationFailedException
     */
    public function setNamespace(string $namespace): void
    {
        try {
            $this->namespace = $this->validateUrl($namespace);
        } catch (ValidationFailedException $exception) {
            throw $exception;
        }
    }

    /**
     * Set the schema URL for this format.
     *
     * @param string $schema The schema URL
     *
     * @return void
     *
     * @throws ValidationFailedException
     */
    public function setSchema(string $schema): void
    {
        try {
            $this->xmlSchema = $this->validateUrl($schema);
        } catch (ValidationFailedException $exception) {
            throw $exception;
        }
    }

    /**
     * Get new entity of format.
     *
     * @param string $prefix The metadata prefix
     * @param string $namespace The format's namespace URI
     * @param string $schema The format's schema URL
     *
     * @throws ValidationFailedException
     */
    public function __construct(string $prefix, string $namespace, string $schema)
    {
        try {
            $this->prefix = $this->validateRegEx($prefix, '/^[A-Za-z0-9\-_\.!~\*\'\(\)]+$/');
            $this->setNamespace($namespace);
            $this->setSchema($schema);
            $this->records = new ArrayCollection();
        } catch (ValidationFailedException $exception) {
            throw $exception;
        }
    }
}
