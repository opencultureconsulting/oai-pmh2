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
            $this->namespace = $this->validateUrl($namespace);
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
            $this->xmlSchema = $this->validateUrl($schema);
        } catch (ValidationFailedException $exception) {
            throw $exception;
        }
    }

    /**
     * Validate metadata prefix.
     *
     * @param string $prefix The metadata prefix
     *
     * @return string The validated prefix
     *
     * @throws ValidationFailedException
     */
    protected function validatePrefix(string $prefix): string
    {
        $prefix = trim($prefix);
        $validator = Validation::createValidator();
        $violations = $validator->validate(
            $prefix,
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
        return $prefix;
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
    protected function validateUrl(string $url): string
    {
        $url = trim($url);
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
            $this->prefix = $this->validatePrefix($prefix);
            $this->setNamespace($namespace);
            $this->setSchema($schema);
        } catch (ValidationFailedException $exception) {
            throw $exception;
        }
    }
}
