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
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads, validates and provides configuration settings.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 *
 * @property-read non-empty-string $repositoryName Common name of this repository
 * @property-read non-empty-string $adminEmail Repository contact's e-mail address
 * @property-read non-empty-string $database Database's data source name (DSN)
 * @property-read array<non-empty-string, array{namespace: non-empty-string, schema: non-empty-string}> $metadataPrefix Array of served metadata prefixes
 * @property-read 'no'|'transient'|'persistent' $deletedRecords Repository's deleted records policy
 * @property-read int<1, 100> $maxRecords Maximum number of records served per request
 * @property-read int<300, 86400> $tokenValid Number of seconds resumption tokens are valid
 *
 * @phpstan-type ConfigArray = array{
 *     repositoryName: non-empty-string,
 *     adminEmail: non-empty-string,
 *     database: non-empty-string,
 *     metadataPrefix: array<non-empty-string, array{namespace: non-empty-string, schema: non-empty-string}>,
 *     deletedRecords: 'no'|'transient'|'persistent',
 *     maxRecords: int<1, 100>,
 *     tokenValid: int<300, 86400>
 * }
 */
final class Configuration
{
    use Singleton;

    /**
     * Fully qualified path to the custom configuration file.
     *
     * @var non-empty-string
     */
    protected const CONFIG_FILE = __DIR__ . '/../config/config.yml';

    /**
     * Fully qualified path to the default configuration file.
     *
     * @var non-empty-string
     */
    protected const DEFAULT_CONFIG_FILE = __DIR__ . '/../config/config.dist.yml';

    /**
     * The configuration settings.
     *
     * @var ConfigArray
     */
    protected readonly array $settings;

    /**
     * Get configuration from file.
     *
     * @param string $configFile The path to the configuration file
     *
     * @return mixed The parsed YAML from the file
     *
     * @throws FileNotFoundException if the file does not exist
     * @throws ParseException if the YAML is not valid
     */
    protected function getConfiguration(string $configFile): mixed
    {
        $configPath = Path::canonicalize($configFile);
        if (!is_readable($configPath)) {
            throw new FileNotFoundException(
                'Configuration file not found or not readable.',
                500,
                null,
                $configPath
            );
        }
        return Yaml::parseFile($configPath);
    }

    /**
     * Load and validate configuration settings from YAML file.
     */
    private function __construct()
    {
        $config = array_merge(
            (array) $this->getConfiguration(self::DEFAULT_CONFIG_FILE),
            (array) $this->getConfiguration(self::CONFIG_FILE)
        );
        /** @psalm-suppress TypeDoesNotContainType */
        ConfigurationValidator::validate($config);
        $this->settings = $config;
    }

    /**
     * Magic getter for $this->settings.
     *
     * @param non-empty-string $name The setting to retrieve
     *
     * @return ?mixed The setting or NULL
     */
    public function __get(string $name): mixed
    {
        return $this->settings[$name] ?? null;
    }
}
