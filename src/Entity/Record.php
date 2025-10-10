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

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use OCC\OaiPmh2\Entity;
use OCC\OaiPmh2\Repository\RecordRepository;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Doctrine/ORM Entity for records.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 */
#[ORM\Entity(repositoryClass: RecordRepository::class)]
#[ORM\Table(name: 'records')]
#[ORM\Index(columns: ['identifier'])]
#[ORM\Index(columns: ['metadataPrefix'])]
#[ORM\Index(columns: ['lastChanged'])]
#[ORM\Index(columns: ['metadataPrefix', 'lastChanged'])]
class Record extends Entity
{
    /**
     * The record identifier.
     */
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 255)]
    private string $identifier;

    /**
     * The associated format.
     */
    #[ORM\Id]
    #[ORM\ManyToOne(
        targetEntity: Format::class,
        fetch: 'EXTRA_LAZY',
        inversedBy: 'records'
    )]
    #[ORM\JoinColumn(
        referencedColumnName: 'prefix',
        onDelete: 'CASCADE'
    )]
    private Format $metadataPrefix;

    /**
     * The date and time of last change.
     */
    #[ORM\Column(type: 'datetime')]
    private DateTime $lastChanged;

    /**
     * The record's content.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $content = null;

    /**
     * Collection of associated sets.
     *
     * @var Collection<non-empty-string, Set>
     */
    #[ORM\ManyToMany(
        targetEntity: Set::class,
        inversedBy: 'records',
        cascade: ['persist'],
        fetch: 'EXTRA_LAZY',
        indexBy: 'spec'
    )]
    #[ORM\JoinTable(name: 'records_sets')]
    #[ORM\JoinColumn(name: 'record_identifier', referencedColumnName: 'identifier')]
    #[ORM\JoinColumn(name: 'record_prefix', referencedColumnName: 'metadataPrefix')]
    #[ORM\InverseJoinColumn(name: 'set_spec', referencedColumnName: 'spec')]
    private Collection $sets;

    /**
     * Associate the record with a set.
     *
     * @param Set $set The set
     *
     * @return void
     */
    public function addSet(Set $set): void
    {
        if (!$this->sets->contains($set)) {
            $this->sets->add($set);
            $set->addRecord($this);
        }
    }

    /**
     * Get the record's content.
     *
     * @return ?string The record's content or NULL if deleted
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * Get the associated format.
     *
     * @return Format The associated format
     */
    public function getFormat(): Format
    {
        return $this->metadataPrefix;
    }

    /**
     * Get the record identifier.
     *
     * @return string The record identifier
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * Get the date and time of last change.
     *
     * @return DateTime The datetime of last change
     */
    public function getLastChanged(): DateTime
    {
        return $this->lastChanged;
    }

    /**
     * Get a associated set.
     *
     * @param non-empty-string $setSpec The set's spec
     *
     * @return ?Set The Set or NULL on failure
     */
    public function getSet(string $setSpec): ?Set
    {
        return $this->sets->get($setSpec);
    }

    /**
     * Get a collection of associated sets.
     *
     * @return Collection<non-empty-string, Set> The associated sets
     */
    public function getSets(): Collection
    {
        return $this->sets;
    }

    /**
     * Whether this record has any content.
     *
     * @return bool TRUE if content exists, FALSE otherwise
     *
     * @phpstan-assert-if-true !null $this->getContent()
     */
    public function hasContent(): bool
    {
        return isset($this->content);
    }

    /**
     * Remove record from set.
     *
     * @param Set $set The set
     *
     * @return void
     */
    public function removeSet(Set $set): void
    {
        if ($this->sets->contains($set)) {
            $this->sets->removeElement($set);
            $set->removeRecord($this);
        }
    }

    /**
     * Set record's content.
     *
     * @param ?string $data The record's content or NULL to mark as deleted
     * @param bool $validate Should the input be validated?
     *
     * @return void
     *
     * @throws ValidationFailedException
     */
    public function setContent(?string $data = null, bool $validate = true): void
    {
        if (isset($data)) {
            $data = trim($data);
            if ($validate) {
                try {
                    $data = $this->validateXml($data);
                } catch (ValidationFailedException $exception) {
                    throw $exception;
                }
            }
        }
        $this->content = $data;
    }

    /**
     * Set format of the record.
     *
     * @param Format $format The record's format
     *
     * @return void
     */
    protected function setFormat(Format $format): void
    {
        $this->metadataPrefix = $format;
    }

    /**
     * Set date and time of last change.
     *
     * @param ?DateTime $dateTime The datetime of last change or NULL for "NOW"
     *
     * @return void
     */
    public function setLastChanged(?DateTime $dateTime = null): void
    {
        if (!isset($dateTime)) {
            $dateTime = new DateTime();
        }
        $this->lastChanged = $dateTime;
    }

    /**
     * Get new entity of record.
     *
     * @param string $identifier The record identifier
     * @param Format $format The format
     * @param ?string $data The record's content
     * @param ?DateTime $lastChanged The date of last change
     *
     * @throws ValidationFailedException
     */
    public function __construct(string $identifier, Format $format, ?string $data = null, ?DateTime $lastChanged = null)
    {
        try {
            $this->identifier = $this->validateRegEx(
                $identifier,
                // xs:anyURI
                '/^(?=.)(([a-zA-Z][0-9a-zA-Z+\\-\\.]*:)?\/{0,2}[0-9a-zA-Z;\/?:@&=+$\\.\\-_!~*\'()%]+)?(#[0-9a-zA-Z;\/?:@&=+$\\.\\-_!~*\'()%]+)?$/'
            );
            $this->setFormat($format);
            $this->setContent($data);
            $this->setLastChanged($lastChanged);
            $this->sets = new ArrayCollection();
        } catch (ValidationFailedException $exception) {
            throw $exception;
        }
    }

    /**
     * Get the record's content.
     *
     * @return string The record's content
     */
    public function __toString(): string
    {
        return $this->content ?? '';
    }
}
