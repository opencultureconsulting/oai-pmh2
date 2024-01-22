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
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Doctrine/ORM Entity for sets.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package opencultureconsulting/oai-pmh2
 */
#[ORM\Entity]
#[ORM\Table(name: 'sets')]
class Set extends Entity
{
    /**
     * The unique set spec.
     */
    #[ORM\Id]
    #[ORM\Column(type: 'string')]
    private string $spec;

    /**
     * The name of the set.
     */
    #[ORM\Column(type: 'string')]
    private string $name;

    /**
     * A description of the set.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /**
     * Collection of associated records.
     *
     * @var Collection<int, Record>
     */
    #[ORM\ManyToMany(targetEntity: Record::class, mappedBy: 'sets', fetch: 'EXTRA_LAZY')]
    private Collection $records;

    /**
     * Update bi-directional association with records.
     *
     * @param Record $record The record to add to this set
     *
     * @return void
     */
    public function addRecord(Record $record): void
    {
        if (!$this->records->contains($record)) {
            $this->records->add($record);
            $record->addSet($this);
        }
    }

    /**
     * Get the description of this set.
     *
     * @return ?string The set description or NULL
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Get the name of this set.
     *
     * @return string The set name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the set spec.
     *
     * @return string The set spec
     */
    public function getSpec(): string
    {
        return $this->spec;
    }

    /**
     * Get a collection of associated records.
     *
     * @return array<int, Record> The associated records
     */
    public function getRecords(): array
    {
        return $this->records->toArray();
    }

    /**
     * Whether this set has a description.
     *
     * @return bool TRUE if description exists, FALSE otherwise
     */
    public function hasDescription(): bool
    {
        return isset($this->description);
    }

    /**
     * Whether this set contains any records.
     *
     * @return bool TRUE if empty or FALSE otherwise
     */
    public function isEmpty(): bool
    {
        return count($this->records) === 0;
    }

    /**
     * Update bi-directional association with records.
     *
     * @param Record $record The record to remove from this set
     *
     * @return void
     */
    public function removeRecord(Record $record): void
    {
        if ($this->records->contains($record)) {
            $this->records->removeElement($record);
            $record->removeSet($this);
        }
    }

    /**
     * Set the description for this set.
     *
     * @param ?string $description The description
     *
     * @return void
     *
     * @throws ValidationFailedException
     */
    public function setDescription(?string $description): void
    {
        if (isset($description)) {
            $description = trim($description);
            try {
                $description = $this->validateXml($description);
            } catch (ValidationFailedException $exception) {
                throw $exception;
            }
        }
        $this->description = $description;
    }

    /**
     * Set the name for this set.
     *
     * @param ?string $name The name (defaults to spec)
     *
     * @return void
     */
    public function setName(?string $name): void
    {
        $this->name = $name ?? $this->getSpec();
    }

    /**
     * Get new entity of set.
     *
     * @param string $spec The set spec
     * @param ?string $name The name of the set (defaults to spec)
     * @param ?string $description The description of the set
     *
     * @throws ValidationFailedException
     */
    public function __construct(string $spec, ?string $name = null, string $description = null)
    {
        try {
            $this->spec = $this->validateRegEx($spec, '/^([A-Za-z0-9\-_\.!~\*\'\(\)])+(:[A-Za-z0-9\-_\.!~\*\'\(\)]+)*$/');
            $this->setName($name);
            $this->setDescription($description);
            $this->records = new ArrayCollection();
        } catch (ValidationFailedException $exception) {
            throw $exception;
        }
    }

    /**
     * Get the set's string representation for comparison.
     *
     * @return string The set's unique spec
     */
    public function __toString(): string
    {
        return $this->getSpec();
    }
}
