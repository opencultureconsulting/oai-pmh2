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
use OCC\OaiPmh2\Entity\Format;
use OCC\OaiPmh2\Entity\Record;
use OCC\OaiPmh2\Entity\Set;
use OCC\OaiPmh2\Validator\MetadataPrefixValidator;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Base class for all OAI-PMH console commands.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 *
 * @psalm-type CliArguments = array{
 *     identifier: string,
 *     format: string,
 *     file: string,
 *     sets?: list<string>,
 *     setSpec: string,
 *     setName: string,
 *     idColumn: string,
 *     contentColumn: string,
 *     dateColumn: string,
 *     setColumn: string,
 *     noValidation: bool,
 *     purge: bool,
 *     force: bool
 * }
 */
abstract class Console extends Command
{
    /**
     * This holds the command's arguments and options.
     *
     * @var CliArguments
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
        /** @var Format */
        $format = $this->em->getMetadataFormat($this->arguments['format']);
        $record = new Record($identifier, $format, null, $lastChanged);
        $record->setContent($content, !($this->arguments['noValidation'] ?? false));
        foreach ($sets as $setSpec) {
            $set = $this->em->getSet($setSpec);
            if (isset($set)) {
                $record->addSet($set);
            } elseif (Configuration::getInstance()->autoSets) {
                $record->addSet(new Set($setSpec));
            }
        }
        $this->em->addOrUpdate($record, get_class($this) === CsvImportCommand::class);
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
        $batchSize = Configuration::getInstance()->batchSize;
        if ($batchSize === 0 && (memory_get_usage() / $this->getPhpMemoryLimit()) > 0.4) {
            $this->flushAndClear();
        } elseif ($batchSize > 0 && $count % $batchSize === 0) {
            $this->flushAndClear();
        }
    }

    /**
     * Clears the result cache.
     *
     * @return void
     */
    protected function clearResultCache(): void
    {
        /** @var Application */
        $app = $this->getApplication();
        $app->doRun(
            new ArrayInput([
                'command' => 'orm:clear-cache:result',
                '--flush' => true
            ]),
            new NullOutput()
        );
    }

    /**
     * Executes the current command.
     *
     * @param InputInterface $input The input
     * @param OutputInterface $output The output
     *
     * @return int 0 if everything went fine, or an error code
     */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        /** @psalm-suppress PropertyTypeCoercion */
        /** @phpstan-ignore assign.propertyType */
        $this->arguments = array_merge($input->getArguments(), $input->getOptions());
        if (!$this->validateInput()) {
            return Command::INVALID;
        }
        return Command::SUCCESS;
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
     * Validate input arguments.
     *
     * @return bool Whether the inputs validate
     */
    protected function validateInput(): bool
    {
        if (array_key_exists('format', $this->arguments)) {
            if (MetadataPrefixValidator::validate($this->arguments['format'])->count() > 0) {
                $this->io->getErrorStyle()->error(
                    sprintf('Metadata format "%s" is not supported.', $this->arguments['format'])
                );
                return false;
            }
        }
        if (array_key_exists('file', $this->arguments) && !is_readable($this->arguments['file'])) {
            $this->io->getErrorStyle()->error(
                sprintf('File "%s" not found or not readable.', $this->arguments['file'])
            );
            return false;
        }
        return true;
    }

    /**
     * Create new console command instance.
     *
     * @param ?string $name The name of the command
     *                      passing NULL means it must be set in configure()
     */
    public function __construct(?string $name = null)
    {
        // Don't time out during CLI commands
        set_time_limit(0);
        $this->em = EntityManager::getInstance();
        parent::__construct($name);
    }
}
