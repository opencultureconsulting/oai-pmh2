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

/**
 * Doctrine/ORM Entity for sets.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package opencultureconsulting/oai-pmh2
 */
#[ORM\Entity]
#[ORM\Table(name: 'sets')]
class Set
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
    #[ORM\Column(type: 'text')]
    private string $description = '';

    /**
     * Collection of associated records.
     *
     * @var Collection<int, Record>
     */
    #[ORM\ManyToMany(targetEntity: Record::class, mappedBy: 'sets')]
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
        }
    }

    /**
     * Get the description of this set.
     *
     * @return string The set description
     */
    public function getDescription(): string
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
     * @return Collection<int, Record> The associated records
     */
    public function getRecords(): Collection
    {
        return $this->records;
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
        $this->records->removeElement($record);
    }

    /**
     * Set the description for this set.
     *
     * @param string $description The description
     *
     * @return void
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * Get new entity of set.
     *
     * @param string $spec The set spec
     * @param string $name The name of the set
     * @param string $description The description of the set
     */
    public function __construct(string $spec, string $name, string $description = '')
    {
        $this->spec = $spec;
        $this->name = $name;
        $this->setDescription($description);
        $this->records = new ArrayCollection();
    }
}
