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

namespace OCC\OaiPmh2\Console;

use DateTime;
use OCC\OaiPmh2\Console;
use OCC\OaiPmh2\Entity\Format;
use OCC\OaiPmh2\Entity\Record;
use OCC\OaiPmh2\Entity\Set;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Import records into database from a CSV file.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 *
 * @psalm-type ColumnMapping = array{
 *     idColumn: int,
 *     contentColumn: int,
 *     dateColumn: ?int,
 *     setColumn: ?int
 * }
 */
#[AsCommand(
    name: 'oai:records:import:csv',
    description: 'Import records from a CSV file'
)]
final class CsvImportCommand extends Console
{
    /**
     * Configures the current command.
     *
     * @return void
     */
    #[\Override]
    protected function configure(): void
    {
        $this->addArgument(
            'format',
            InputArgument::REQUIRED,
            'The format (metadata prefix) of the records.',
            null,
            function (): array {
                return $this->em->getMetadataFormats()->getKeys();
            }
        );
        $this->addArgument(
            'file',
            InputArgument::REQUIRED,
            'The CSV file containing the records.'
        );
        $this->addOption(
            'idColumn',
            'i',
            InputOption::VALUE_OPTIONAL,
            'Name of the CSV column which holds the records\' identifier.',
            'identifier'
        );
        $this->addOption(
            'contentColumn',
            'c',
            InputOption::VALUE_OPTIONAL,
            'Name of the CSV column which holds the records\' content.',
            'content'
        );
        $this->addOption(
            'dateColumn',
            'd',
            InputOption::VALUE_OPTIONAL,
            'Optional: Name of the CSV column which holds the records\' datetime of last change.',
            'lastChanged'
        );
        $this->addOption(
            'setColumn',
            's',
            InputOption::VALUE_OPTIONAL,
            'Optional: Name of the CSV column which holds the comma-separated list of the records\' sets.',
            'sets'
        );
        $this->addOption(
            'noValidation',
            null,
            InputOption::VALUE_NONE,
            'Optional: Skip content validation (improves ingest performance for large record sets).'
        );
        parent::configure();
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
        if (!$this->validateInput($input, $output)) {
            return Command::INVALID;
        }

        /** @var resource */
        $file = fopen($this->arguments['file'], 'r');

        $columnMapping = $this->getColumnNames($input, $output, $file);

        if (!isset($columnMapping)) {
            return Command::FAILURE;
        }

        $count = 0;
        $progressIndicator = new ProgressIndicator($output, null, 100, ['⠏', '⠛', '⠹', '⢸', '⣰', '⣤', '⣆', '⡇']);
        $progressIndicator->start('Importing...');

        while ($row = fgetcsv($file, null, ",", "\"", "\\")) {
            if (!is_null($row[0])) {
                /** @var Format */
                $format = $this->em->getMetadataFormat($this->arguments['format']);
                /** @phpstan-ignore-next-line - see https://github.com/phpstan/phpstan/issues/12195 */
                $record = new Record($row[$columnMapping['idColumn']], $format);
                /** @phpstan-ignore-next-line - see https://github.com/phpstan/phpstan/issues/12195 */
                if (strlen(trim($row[$columnMapping['contentColumn']])) > 0) {
                    $record->setContent($row[$columnMapping['contentColumn']], !$this->arguments['noValidation']);
                }
                if (isset($columnMapping['dateColumn'])) {
                    /** @phpstan-ignore-next-line - see https://github.com/phpstan/phpstan/issues/12195 */
                    $record->setLastChanged(new DateTime($row[$columnMapping['dateColumn']]));
                }
                if (isset($columnMapping['setColumn'])) {
                    $sets = $row[$columnMapping['setColumn']];
                    /** @phpstan-ignore-next-line - see https://github.com/phpstan/phpstan/issues/12195 */
                    foreach (explode(',', $sets) as $set) {
                        /** @var Set */
                        $setSpec = $this->em->getSet(trim($set));
                        $record->addSet($setSpec);
                    }
                }
                $this->em->addOrUpdate($record, true);

                ++$count;
                $progressIndicator->advance();
                $progressIndicator->setMessage('Importing... ' . (string) $count . ' records processed.');
                $this->checkMemoryUsage();
            }
        }
        $this->em->flush();
        $this->em->pruneOrphanedSets();

        $progressIndicator->finish('All done!');

        fclose($file);

        $this->clearResultCache();

        $output->writeln([
            '',
            sprintf(
                ' [OK] %d records with metadata prefix "%s" were imported successfully! ',
                $count,
                $this->arguments['format']
            ),
            ''
        ]);
        return Command::SUCCESS;
    }

    /**
     * Get the column numbers of CSV.
     *
     * @param InputInterface $input The input
     * @param OutputInterface $output The output
     * @param resource $file The handle for the CSV file
     *
     * @return ?ColumnMapping The mapped columns or NULL in case of an error
     */
    protected function getColumnNames(InputInterface $input, OutputInterface $output, $file): ?array
    {
        /** @var array{idColumn: string, contentColumn: string, dateColumn: string, setColumn: string} */
        $columns = [
            'idColumn' => $input->getOption('idColumn'),
            'contentColumn' => $input->getOption('contentColumn'),
            'dateColumn' => $input->getOption('dateColumn'),
            'setColumn' => $input->getOption('setColumn')
        ];

        $headers = fgetcsv($file, null, ",", "\"", "\\");
        if (!is_array($headers) || is_null($headers[0])) {
            $output->writeln([
                '',
                sprintf(
                    ' [ERROR] File "%s" does not contain valid CSV. ',
                    stream_get_meta_data($file)['uri'] ?? 'unknown'
                ),
                ''
            ]);
            return null;
        }
        /** @phpstan-ignore-next-line - see https://github.com/phpstan/phpstan/issues/12195 */
        $headers = array_flip($headers);

        $callback = function (string $column) use ($headers): ?int {
            /** @psalm-suppress InvalidArgument */
            return array_key_exists($column, $headers) ? $headers[$column] : null;
        };

        $columns = array_map($callback, $columns);

        if (!isset($columns['idColumn']) || !isset($columns['contentColumn'])) {
            $output->writeln([
                '',
                sprintf(
                    ' [ERROR] File "%s" does not contain mandatory columns. ',
                    stream_get_meta_data($file)['uri'] ?? 'unknown'
                ),
                ''
            ]);
            return null;
        }
        return $columns;
    }
}
