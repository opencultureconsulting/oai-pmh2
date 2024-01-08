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

namespace OCC\OaiPmh2\Entity;

use Doctrine\ORM\Mapping as ORM;
use OCC\OaiPmh2\Entity;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Doctrine/ORM Entity for formats.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package opencultureconsulting/oai-pmh2
 */
#[ORM\Entity]
#[ORM\Table(name: 'formats')]
class Format extends Entity
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
     * Get the format's schema URL.
     *
     * @return string The schema URL
     */
    public function getSchema(): string
    {
        return $this->xmlSchema;
    }

    /**
     * Set the format's namespace URI.
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
            $this->namespace = $this->validateUri($namespace);
        } catch (ValidationFailedException $exception) {
            throw $exception;
        }
    }

    /**
     * Set the format's schema URL.
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
            $this->xmlSchema = $this->validateUri($schema);
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
            $this->prefix = $this->validateNoWhitespace($prefix);
            $this->setNamespace($namespace);
            $this->setSchema($schema);
        } catch (ValidationFailedException $exception) {
            throw $exception;
        }
    }
}
