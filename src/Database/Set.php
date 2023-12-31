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
     * @return array<int, Record> The associated records
     */
    public function getRecords(): array
    {
        return $this->records->toArray();
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
     * @param string $description The description
     *
     * @return void
     */
    public function setDescription(string $description): void
    {
        $this->description =  trim($description);
    }

    /**
     * Validate set spec.
     *
     * @param string $spec The set spec
     *
     * @return string The validated spec
     *
     * @throws ValidationFailedException
     */
    protected function validate(string $spec): string
    {
        $spec = trim($spec);
        $validator = Validation::createValidator();
        $violations = $validator->validate(
            $spec,
            [
                new Assert\Regex([
                    'pattern' => '/\s/',
                    'match' => false,
                    'message' => 'This value contains whitespaces.'
                ]),
                new Assert\NotBlank()
            ]
        );
        if ($violations->count() > 0) {
            throw new ValidationFailedException(null, $violations);
        }
        return $spec;
    }

    /**
     * Get new entity of set.
     *
     * @param string $spec The set spec
     * @param string $name The name of the set
     * @param string $description The description of the set
     *
     * @throws ValidationFailedException
     */
    public function __construct(string $spec, string $name, string $description = '')
    {
        try {
            $this->spec = $this->validate($spec);
            $this->name = trim($name);
            $this->setDescription($description);
            $this->records = new ArrayCollection();
        } catch (ValidationFailedException $exception) {
            throw $exception;
        }
    }
}
