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
 * @extends Console<array{
 *     file: non-empty-string,
 *     format: non-empty-string,
 *     idColumn: non-empty-string,
 *     contentColumn: non-empty-string,
 *     dateColumn: non-empty-string,
 *     setColumn: non-empty-string,
 *     noValidation: bool,
 *     purge: bool
 * }>
 * @phpstan-type ColumnMapping = array{
 *     idColumn: non-negative-int,
 *     contentColumn: non-negative-int,
 *     dateColumn: int<-1, max>,
 *     setColumn: int<-1, max>
 * }
 */
#[AsCommand(
    name: 'oai:import:csv',
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
            'The format (metadata prefix) of the records',
            null,
            function (): array {
                return $this->em->getMetadataFormats()->getKeys();
            }
        );
        $this->addArgument(
            'file',
            InputArgument::REQUIRED,
            'The CSV file containing the records'
        );
        $this->addOption(
            'idColumn',
            'i',
            InputOption::VALUE_OPTIONAL,
            'Name of the CSV column which holds the records\' identifier',
            'identifier'
        );
        $this->addOption(
            'contentColumn',
            'c',
            InputOption::VALUE_OPTIONAL,
            'Name of the CSV column which holds the records\' content',
            'content'
        );
        $this->addOption(
            'dateColumn',
            'd',
            InputOption::VALUE_OPTIONAL,
            'Name of the CSV column which holds the records\' datetime of last change',
            'lastChanged'
        );
        $this->addOption(
            'setColumn',
            's',
            InputOption::VALUE_OPTIONAL,
            'Name of the CSV column which holds the comma-separated list of the records\' sets',
            'sets'
        );
        $this->addOption(
            'noValidation',
            null,
            InputOption::VALUE_NONE,
            'Skip content validation (improves ingest performance for large record sets)'
        );
        $this->addOption(
            'purge',
            null,
            InputOption::VALUE_NONE,
            'Purge all existing records with the same metadata prefix before importing'
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
        if (!$this->em->getMetadataFormats()->containsKey($this->arguments['format'])) {
            $this->io->getErrorStyle()->error(
                sprintf('Metadata format "%s" is not supported.', $this->arguments['format'])
            );
            return Command::INVALID;
        }

        $file = fopen($this->arguments['file'], 'r');
        if ($file === false) {
            $this->io->getErrorStyle()->error(
                sprintf('File "%s" not found or not readable.', $this->arguments['file'])
            );
            return Command::INVALID;
        }

        $columns = $this->getColumnMapping($file);
        if (!isset($columns)) {
            return Command::FAILURE;
        }

        $this->purgeRecords();

        $count = 0;
        $progressIndicator = new ProgressIndicator($this->io, null, 100, ['⠏', '⠛', '⠹', '⢸', '⣰', '⣤', '⣆', '⡇']);
        $progressIndicator->start('Importing...');

        while ($row = fgetcsv($file, null, ",", "\"", "\\")) {
            if (!is_null($row[0])) {
                $this->addOrUpdateRecord(
                    /** @phpstan-ignore-next-line - see https://github.com/phpstan/phpstan/issues/12195 */
                    $row[$columns['idColumn']],
                    /** @phpstan-ignore-next-line - see https://github.com/phpstan/phpstan/issues/12195 */
                    trim($row[$columns['contentColumn']]) ?: null,
                    $columns['dateColumn'] >= 0 ? new DateTime($row[$columns['dateColumn']] ?? 'now') : null,
                    /** @phpstan-ignore arrayFilter.strict */
                    $columns['setColumn'] >= 0 ? array_filter(explode(',', $row[$columns['setColumn']] ?? '')) : []
                );

                ++$count;
                $progressIndicator->advance();
                $progressIndicator->setMessage('Importing... ' . (string) $count . ' records processed.');

                $this->checkMemoryUsage($count);
            }
        }

        $this->flushAndClear();
        $progressIndicator->finish('All done! ' . (string) $count . ' records imported.');

        fclose($file);

        $this->io->writeln('Pruning potentially orphaned sets...');
        $this->em->pruneOrphanedSets();

        $this->clearResultCache();

        $this->io->success(
            sprintf(
                '%d records with metadata prefix "%s" were imported successfully!',
                $count,
                $this->arguments['format']
            )
        );
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
        $columns = [
            'idColumn' => $this->arguments['idColumn'],
            'contentColumn' => $this->arguments['contentColumn'],
            'dateColumn' => $this->arguments['dateColumn'],
            'setColumn' => $this->arguments['setColumn']
        ];
        $filename = stream_get_meta_data($file)['uri'] ?? '';

        $headers = fgetcsv($file, null, ",", "\"", "\\");
        if (!is_array($headers) || is_null($headers[0])) {
            $this->io->getErrorStyle()->error(
                sprintf('File "%s" does not contain valid CSV.', $filename ?: 'unknown')
            );
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
            $this->io->getErrorStyle()->error(
                sprintf('File "%s" does not contain mandatory columns.', $filename ?: 'unknown')
            );
            return null;
        }
        return $columns;
    }

    /**
     * Purge existing records before importing new ones.
     *
     * @return void
     */
    protected function purgeRecords(): void
    {
        if ($this->arguments['purge']) {
            $this->io->writeln(
                sprintf(
                    'Purging existing records with metadata prefix "%s"...',
                    $this->arguments['format']
                )
            );
            $this->em->purgeRecords($this->arguments['format']);
            $this->clearResultCache();
        }
    }
}
