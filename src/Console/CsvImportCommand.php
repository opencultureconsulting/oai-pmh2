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

namespace OCC\OaiPmh2\Console;

use DateTime;
use OCC\OaiPmh2\ConsoleCommand;
use OCC\OaiPmh2\Database;
use OCC\OaiPmh2\Database\Format;
use OCC\OaiPmh2\Database\Record;
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
 * @package opencultureconsulting/oai-pmh2
 */
#[AsCommand(
    name: 'oai:records:import:csv',
    description: 'Import records from a CSV file'
)]
class CsvImportCommand extends ConsoleCommand
{
    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->addArgument(
            'format',
            InputArgument::REQUIRED,
            'The format (metadata prefix) of the records.',
            null,
            function (): array {
                return array_keys(Database::getInstance()->getMetadataFormats()->getQueryResult());
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
            'Name of the CSV column which holds the records\' datetime of last change.',
            'lastChanged'
        );
        $this->addOption(
            'setColumn',
            's',
            InputOption::VALUE_OPTIONAL,
            'Name of the CSV column which holds the records\' sets list.',
            'sets'
        );
        $this->addOption(
            'noValidation',
            null,
            InputOption::VALUE_NONE,
            'Omit content validation (improves performance for large record sets).'
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
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->validateInput($input, $output)) {
            return Command::INVALID;
        }
        $memoryLimit = $this->getMemoryLimit();

        /** @var array<string, string> */
        $arguments = $input->getArguments();
        /** @var Format */
        $format = Database::getInstance()->getEntityManager()->getReference(Format::class, $arguments['format']);
        /** @var bool */
        $noValidation = $input->getOption('noValidation');
        /** @var resource */
        $file = fopen($arguments['file'], 'r');

        $columns = $this->getColumnNames($input, $output, $file);
        if (count($columns) === 0) {
            return Command::INVALID;
        }

        $count = 0;
        $progressIndicator = new ProgressIndicator($output, null, 100, ['⠏', '⠛', '⠹', '⢸', '⣰', '⣤', '⣆', '⡇']);
        $progressIndicator->start('Importing...');

        while ($row = fgetcsv($file)) {
            $record = new Record(
                $row[$columns['idColumn']],
                $format,
                null,
                new DateTime($row[$columns['dateColumn']] ?? 'now')
            );
            if (strlen(trim($row[$columns['contentColumn']])) > 0) {
                $record->setContent($row[$columns['contentColumn']], !$noValidation);
            }
            // TODO: Complete support for sets.
            Database::getInstance()->addOrUpdateRecord($record, true);

            ++$count;
            $progressIndicator->advance();
            $progressIndicator->setMessage('Importing... ' . (string) $count . ' records done.');

            // Flush to database if memory usage reaches 30% of available limit.
            if ((memory_get_usage() / $memoryLimit) > 0.3) {
                Database::getInstance()->flush([Record::class]);
            }
        }
        Database::getInstance()->flush();
        Database::getInstance()->pruneOrphanSets();

        $progressIndicator->finish('All done!');

        fclose($file);

        $this->clearResultCache();

        $output->writeln([
            '',
            sprintf(
                ' [OK] %d records with metadata prefix "%s" were imported successfully! ',
                $count,
                $arguments['format']
            ),
            ''
        ]);
        return Command::SUCCESS;
    }

    /**
     * Get the column names of CSV.
     *
     * @param InputInterface $input The inputs
     * @param OutputInterface $output The output interface
     * @param resource $file The handle for the CSV file
     *
     * @return array<string, int|string> The mapped column names
     */
    protected function getColumnNames(InputInterface $input, OutputInterface $output, $file): array
    {
        /** @var array<string, string> */
        $options = $input->getOptions();

        $columns = [];

        $headers = fgetcsv($file);
        if (!is_array($headers)) {
            $output->writeln([
                '',
                sprintf(
                    ' [ERROR] File "%s" does not contain valid CSV. ',
                    stream_get_meta_data($file)['uri']
                ),
                ''
            ]);
            return [];
        } else {
            $headers = array_flip($headers);
        }
        foreach ($options as $option => $value) {
            if (isset($headers[$value])) {
                $columns[$option] = $headers[$value];
            }
        }

        if (!isset($columns['idColumn']) || !isset($columns['contentColumn'])) {
            $output->writeln([
                '',
                sprintf(
                    ' [ERROR] File "%s" does not contain valid CSV. ',
                    stream_get_meta_data($file)['uri']
                ),
                ''
            ]);
            return [];
        }
        return $columns;
    }

    /**
     * Validate input arguments.
     *
     * @param InputInterface $input The inputs
     * @param OutputInterface $output The output interface
     *
     * @return bool Whether the inputs validate
     */
    protected function validateInput(InputInterface $input, OutputInterface $output): bool
    {
        /** @var array<string, string> */
        $arguments = $input->getArguments();

        $formats = Database::getInstance()->getMetadataFormats()->getQueryResult();
        if (!in_array($arguments['format'], array_keys($formats), true)) {
            $output->writeln([
                '',
                sprintf(
                    ' [ERROR] Metadata format "%s" is not supported. ',
                    $arguments['format']
                ),
                ''
            ]);
            return false;
        }
        if (!is_readable($arguments['file'])) {
            $output->writeln([
                '',
                sprintf(
                    ' [ERROR] File "%s" not found or not readable. ',
                    $arguments['file']
                ),
                ''
            ]);
            return false;
        }
        return true;
    }
}
