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

namespace OCC\OaiPmh2;

use OCC\Basics\Traits\Singleton;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads, validates and provides configuration settings.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package opencultureconsulting/oai-pmh2
 *
 * @property-read string $repositoryName
 * @property-read string $adminEmail
 * @property-read string $database
 * @property-read array $metadataPrefix
 * @property-read int $maxRecords
 * @property-read int $tokenValid
 *
 * @template TKey of string
 * @template TValue
 */
class Configuration
{
    use Singleton;

    /**
     * Fully qualified path to the configuration file.
     *
     * @var string
     */
    protected const CONFIG_FILE = __DIR__ . '/../config/config.yml';

    /**
     * The configuration settings.
     *
     * @var array<TKey, TValue>
     */
    protected readonly array $settings;

    /**
     * Get constraints for configuration array.
     *
     * @return Assert\Collection The collection of constraints
     */
    protected function getValidationConstraints(): Assert\Collection
    {
        return new Assert\Collection([
            'repositoryName' => [
                new Assert\Type('string'),
                new Assert\NotBlank()
            ],
            'adminEmail' => [
                new Assert\Type('string'),
                new Assert\Email(['mode' => 'html5']),
                new Assert\NotBlank()
            ],
            'database' => [
                new Assert\Type('string'),
                new Assert\NotBlank()
            ],
            'metadataPrefix' => [
                new Assert\Type('array'),
                new Assert\All([
                    new Assert\Collection([
                        'schema' => [
                            new Assert\Type('string'),
                            new Assert\Url(),
                            new Assert\NotBlank()
                        ],
                        'namespace' => [
                            new Assert\Type('string'),
                            new Assert\Url(),
                            new Assert\NotBlank()
                        ]
                    ])
                ])
            ],
            'maxRecords' => [
                new Assert\Type('int'),
                new Assert\Range([
                    'min' => 1,
                    'max' => 100
                ])
            ],
            'tokenValid' => [
                new Assert\Type('int'),
                new Assert\Range([
                    'min' => 300,
                    'max' => 86400
                ])
            ]
        ]);
    }

    /**
     * Read and validate configuration file.
     *
     * @return array<TKey, TValue> The configuration array
     *
     * @throws FileNotFoundException|ValidationFailedException
     */
    protected function loadConfigFile(): array
    {
        $configPath = Path::canonicalize(self::CONFIG_FILE);
        if (!is_readable($configPath)) {
            throw new FileNotFoundException(
                sprintf(
                    'Configuration file "%s" not found or not readable.',
                    $configPath
                ),
                500,
                null,
                $configPath
            );
        }
        $config = Yaml::parseFile($configPath);
        $validator = Validation::createValidator();
        $violations = $validator->validate($config, $this->getValidationConstraints());
        if ($violations->count() > 0) {
            throw new ValidationFailedException(null, $violations);
        }
        /** @var array<TKey, TValue> $config */
        return $config;
    }

    /**
     * Load and validate configuration settings from YAML file.
     *
     * @throws FileNotFoundException|ValidationFailedException
     */
    private function __construct()
    {
        try {
            $this->settings = $this->loadConfigFile();
        } catch (FileNotFoundException|ValidationFailedException $exception) {
            throw $exception;
        }
    }

    /**
     * Magic getter for $this->settings.
     *
     * @param TKey $name The setting to retrieve
     *
     * @return TValue|null The setting or NULL
     */
    public function __get(string $name): mixed
    {
        return $this->settings[$name] ?? null;
    }
}
