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

use DateTime;
use OCC\OaiPmh2\Console\CsvImportCommand;
use OCC\OaiPmh2\Entity\Record;
use OCC\OaiPmh2\Entity\Set;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Base class for all OAI-PMH console commands.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 *
 * @template CliParameters of array{
 *     file?: non-empty-string,
 *     format?: non-empty-string,
 *     identifier?: non-empty-string,
 *     sets?: non-empty-list<non-empty-string>,
 *     setSpec?: non-empty-string,
 *     setName?: non-empty-string,
 *     idColumn?: non-empty-string,
 *     contentColumn?: non-empty-string,
 *     dateColumn?: non-empty-string,
 *     setColumn?: non-empty-string,
 *     release?: non-empty-string,
 *     dev?: bool,
 *     force?: bool,
 *     list?: bool,
 *     batchSize?: int<0, max>,
 *     createSets?: bool,
 *     noValidation?: bool,
 *     purge?: bool
 * }
 */
abstract class Console extends Command
{
    /**
     * This holds the command's arguments and options.
     *
     * @var CliParameters
     */
    protected array $arguments;

    /**
     * This holds the entity manager singleton.
     */
    protected readonly EntityManager $em;

    /**
     * This holds the I/O interfaces.
     */
    protected SymfonyStyle $io;

    /**
     * This holds the PHP memory limit in bytes.
     *
     * @var positive-int
     */
    protected int $memoryLimit;

    /**
     * Add or update record.
     *
     * @param string $identifier The record identifier
     * @param ?string $content The record's content or NULL to mark as deleted
     * @param ?DateTime $lastChanged The record's date of last change
     * @param string[] $sets The record's set specs
     *
     * @return void
     */
    protected function addOrUpdateRecord(string $identifier, ?string $content, ?DateTime $lastChanged = null, array $sets = []): void
    {
        $format = $this->em->getMetadataFormat($this->arguments['format'] ?? '');
        if (isset($format)) {
            $record = new Record($identifier, $format, null, $lastChanged);
            $record->setContent($content, ($this->arguments['noValidation'] ?? false) === false);
            $createSets = boolval($this->arguments['createSets'] ?? false);
            foreach ($sets as $setSpec) {
                $set = $this->em->getSet($setSpec);
                if (isset($set)) {
                    $record->addSet($set);
                } elseif ($createSets) {
                    $record->addSet(new Set($setSpec));
                }
            }
            $this->em->addOrUpdate($record, get_class($this) === CsvImportCommand::class);
        } else {
            $this->io->getErrorStyle()->error(
                sprintf('Metadata format "%s" is not supported.', $this->arguments['format'] ?? '')
            );
        }
    }

    /**
     * Check memory usage/batch size and flush unit of work if necessary.
     *
     * @param int $count The number of processed records
     *
     * @return void
     */
    protected function checkMemoryUsage(int $count): void
    {
        $batchSize = intval($this->arguments['batchSize'] ?? 0);
        if ($batchSize < 1 && (memory_get_usage() / $this->getPhpMemoryLimit()) > 0.4) {
            $this->flushAndClear();
        } elseif ($batchSize > 0 && $count % $batchSize === 0) {
            $this->flushAndClear();
        }
    }

    /**
     * Clears all Doctrine caches.
     *
     * @return void
     */
    protected function clearAllCaches(): void
    {
        $this->em->getConfiguration()->getMetadataCache()?->clear();
        $this->em->getConfiguration()->getQueryCache()?->clear();
        $this->clearResultCache();
    }

    /**
     * Clears the Doctrine result cache.
     *
     * @return void
     */
    protected function clearResultCache(): void
    {
        $this->em->getConfiguration()->getResultCache()?->clear();
    }

    /**
     * Flush and clear unit of work.
     *
     * @return void
     */
    protected function flushAndClear(): void
    {
        $this->em->flush();
        $this->em->clear();
    }

    /**
     * Gets the PHP memory limit in bytes.
     *
     * @return positive-int The memory limit in bytes
     */
    protected function getPhpMemoryLimit(): int
    {
        if (!isset($this->memoryLimit)) {
            $phpValue = (string) ini_get('memory_limit');
            $limit = (int) $phpValue;
            if ($limit <= 0) {
                // Unlimited memory, so set a sensible default value
                $this->io->getErrorStyle()->warning([
                    'PHP memory limit is set to unlimited, temporarily setting it to 512M.',
                    'You can change this in your php.ini or by using the --memory-limit option.',
                    'Alternatively, it is recommended to set a batch size in config/config.yml',
                    'to avoid high memory usage during import.'
                ]);
                ini_set('memory_limit', '512M');
                return $this->getPhpMemoryLimit();
            } else {
                $unit = strtolower($phpValue[strlen($phpValue) - 1]);
                switch ($unit) {
                    case 'g':
                        $limit *= 1024;
                        // no break
                    case 'm':
                        $limit *= 1024;
                        // no break
                    case 'k':
                        $limit *= 1024;
                }
            }
            $this->memoryLimit = $limit;
        }
        return $this->memoryLimit;
    }

    /**
     * Initializes the command.
     *
     * @param InputInterface $input The input
     * @param OutputInterface $output The output
     *
     * @return void
     */
    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
        /** @psalm-suppress PropertyTypeCoercion */
        /** @phpstan-ignore assign.propertyType */
        $this->arguments = array_merge($input->getArguments(), $input->getOptions());
    }

    /**
     * Create new console command instance.
     *
     * @param ?string $name The name of the command
     */
    public function __construct(?string $name = null)
    {
        // Don't time out during CLI commands
        set_time_limit(0);
        parent::__construct($name);
        $this->em = EntityManager::getInstance();
    }
}
