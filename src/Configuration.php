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

namespace OCC\OaiPmh2;

use OCC\Basics\Traits\Singleton;
use OCC\OaiPmh2\Validator\ConfigurationValidator;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads, validates and provides configuration settings.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 *
 * @property-read string $repositoryName Common name of this repository
 * @property-read string $adminEmail Repository contact's e-mail address
 * @property-read string $database Database's data source name (DSN)
 * @property-read array<string, array<string, string>> $metadataPrefix Array of served metadata prefixes
 * @property-read string $deletedRecords Repository's deleted records policy
 * @property-read int $maxRecords Maximum number of records served per request
 * @property-read int $tokenValid Number of seconds resumption tokens are valid
 * @property-read int $batchSize Batch size for bulk imports
 * @property-read bool $autoSets Whether sets should be created automatically
 *
 * @template TKey of string
 * @template TValue of array|int|string
 */
final class Configuration
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
     * Load and validate configuration settings from YAML file.
     *
     * @throws FileNotFoundException if configuration file does not exist
     * @throws ValidationFailedException if configuration file is not valid
     */
    private function __construct()
    {
        $configPath = Path::canonicalize(self::CONFIG_FILE);
        if (!is_readable($configPath)) {
            throw new FileNotFoundException(
                'Configuration file not found or not readable.',
                500,
                null,
                $configPath
            );
        }
        /** @var array<TKey, TValue> */
        $config = Yaml::parseFile($configPath);
        $violations = ConfigurationValidator::validate($config);
        if ($violations->count() > 0) {
            throw new ValidationFailedException(null, $violations);
        }
        $this->settings = $config;
    }

    /**
     * Magic getter for $this->settings.
     *
     * @param TKey $name The setting to retrieve
     *
     * @return ?TValue The setting or NULL
     */
    public function __get(string $name): mixed
    {
        return $this->settings[$name] ?? null;
    }
}
