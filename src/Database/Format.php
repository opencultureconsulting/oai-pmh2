<?php

/**
 * OAI-PMH 2.0 Data Provider
 * Copyright (C) 2023 Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
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

namespace OCC\OaiPmh2\Database;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validation;

/**
 * Doctrine/ORM Entity for formats.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package opencultureconsulting/oai-pmh2
 */
#[ORM\Entity]
#[ORM\Table(name: 'formats')]
class Format
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
     * Collection of associated records.
     *
     * @var Collection<int, Record>
     */
    #[ORM\OneToMany(targetEntity: Record::class, mappedBy: 'format')]
    private Collection $records;

    /**
     * Update bi-directional association with records.
     *
     * @param Record $record The record to add to this format
     *
     * @return void
     */
    public function addRecord(Record $record): void
    {
        if (!$this->records->contains($record)) {
            $this->records->add($record);
        }
    }

    /**
     * Get the format's namespace URI.
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
     * Get a collection of associated records.
     *
     * @return Collection<int, Record> The associated records
     */
    public function getRecords(): Collection
    {
        return $this->records;
    }

    /**
     * Get the format's schema URL.
     *
     * @return string The schema URL
     */
    public function getSchema(): string
    {
        return $this->xmlSchema;
    }

    /**
     * Update bi-directional association with records.
     *
     * @param Record $record The record to remove from this metadata prefix
     *
     * @return void
     */
    public function removeRecord(Record $record): void
    {
        $this->records->removeElement($record);
    }

    /**
     * Validate namespace and schema URLs.
     *
     * @param string $url The namespace or schema URL
     *
     * @return string The validated URL
     *
     * @throws ValidationFailedException
     */
    protected function validate(string $url): string
    {
        $validator = Validation::createValidator();
        $violations = $validator->validate($url, new Assert\Url());
        if ($violations->count() > 0) {
            throw new ValidationFailedException(null, $violations);
        }
        return $url;
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
            $this->prefix = $prefix;
            $this->namespace = $this->validate($namespace);
            $this->xmlSchema = $this->validate($schema);
            $this->records = new ArrayCollection();
        } catch (ValidationFailedException $exception) {
            throw $exception;
        }
    }
}
