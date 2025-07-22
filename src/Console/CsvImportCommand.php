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
use OCC\OaiPmh2\Configuration;
use OCC\OaiPmh2\Console;
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
 *     dateColumn: int,
 *     setColumn: int
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
            'Optional: Name of the CSV column which holds the records\' identifier.',
            'identifier'
        );
        $this->addOption(
            'contentColumn',
            'c',
            InputOption::VALUE_OPTIONAL,
            'Optional: Name of the CSV column which holds the records\' content.',
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
        if (parent::execute($input, $output) !== Command::SUCCESS) {
            return Command::INVALID;
        }

        /** @var resource */
        $file = fopen($this->arguments['file'], 'r');

        $columnMapping = $this->getColumnMapping($file);
        if (!isset($columnMapping)) {
            return Command::FAILURE;
        }

        $count = 0;
        $batchSize = Configuration::getInstance()->batchSize;
        $progressIndicator = new ProgressIndicator($this->io['output'], null, 100, ['⠏', '⠛', '⠹', '⢸', '⣰', '⣤', '⣆', '⡇']);
        $progressIndicator->start('Importing...');

        while ($row = fgetcsv($file, null, ",", "\"", "\\")) {
            if (!is_null($row[0])) {
                $this->addOrUpdateRecord(
                    /** @phpstan-ignore-next-line - see https://github.com/phpstan/phpstan/issues/12195 */
                    $row[$columnMapping['idColumn']],
                    /** @phpstan-ignore-next-line - see https://github.com/phpstan/phpstan/issues/12195 */
                    trim($row[$columnMapping['contentColumn']]) ?: null,
                    new DateTime($row[$columnMapping['dateColumn']] ?? 'now'),
                    /** @phpstan-ignore arrayFilter.strict */
                    array_filter(explode(',', $row[$columnMapping['setColumn']] ?? ''))
                );

                ++$count;
                $progressIndicator->advance();
                $progressIndicator->setMessage('Importing... ' . (string) $count . ' records processed.');

                // Avoid memory exhaustion by working in batches and checking memory usage.
                if ($batchSize === 0) {
                    if ((memory_get_usage() / $this->getPhpMemoryLimit()) > 0.4) {
                        $progressIndicator->setMessage('Importing... ' . (string) $count . ' records processed. Flushing!');
                        $this->flushAndClear();
                    }
                } else {
                    if ($count % $batchSize === 0) {
                        $progressIndicator->setMessage('Importing... ' . (string) $count . ' records processed. Flushing!');
                        $this->flushAndClear();
                    }
                }
            }
        }

        $progressIndicator->setMessage('Importing... ' . (string) $count . ' records processed. Flushing!');
        $this->flushAndClear();

        $progressIndicator->setMessage('Pruning potentially orphaned sets.');
        $this->em->pruneOrphanedSets();

        $progressIndicator->finish('All done!');

        fclose($file);

        $this->clearResultCache();

        $this->io['output']->writeln([
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
     * @param resource $file The handle for the CSV file
     *
     * @return ?ColumnMapping The mapped columns or NULL in case of an error
     */
    protected function getColumnMapping($file): ?array
    {
        /** @var array{idColumn: string, contentColumn: string, dateColumn: string, setColumn: string} */
        $columns = [
            'idColumn' => $this->io['input']->getOption('idColumn'),
            'contentColumn' => $this->io['input']->getOption('contentColumn'),
            'dateColumn' => $this->io['input']->getOption('dateColumn'),
            'setColumn' => $this->io['input']->getOption('setColumn')
        ];
        $filename = stream_get_meta_data($file)['uri'] ?? '';

        $headers = fgetcsv($file, null, ",", "\"", "\\");
        if (!is_array($headers) || is_null($headers[0])) {
            $this->io['output']->writeln([
                '',
                sprintf(
                    ' [ERROR] File "%s" does not contain valid CSV. ',
                    $filename ?: 'unknown'
                ),
                ''
            ]);
            return null;
        }
        /** @phpstan-ignore-next-line - see https://github.com/phpstan/phpstan/issues/12195 */
        $headers = array_flip($headers);

        $callback = function (string $column) use ($headers): int {
            /** @psalm-suppress InvalidArgument */
            return array_key_exists($column, $headers) ? $headers[$column] : -1;
        };

        $columns = array_map($callback, $columns);

        if ($columns['idColumn'] === -1 || $columns['contentColumn'] === -1) {
            $this->io['output']->writeln([
                '',
                sprintf(
                    ' [ERROR] File "%s" does not contain mandatory columns. ',
                    $filename ?: 'unknown'
                ),
                ''
            ]);
            return null;
        }
        return $columns;
    }
}
