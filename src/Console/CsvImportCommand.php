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
use OCC\OaiPmh2\Console;
use OCC\OaiPmh2\Database;
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
 * @package opencultureconsulting/oai-pmh2
 */
#[AsCommand(
    name: 'oai:records:import:csv',
    description: 'Import records from a CSV file'
)]
class CsvImportCommand extends Console
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
            'Name of the CSV column which holds the comma-separated list of the records\' sets.',
            'sets'
        );
        $this->addOption(
            'noValidation',
            null,
            InputOption::VALUE_NONE,
            'Skip content validation (improves performance for large record sets).'
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
            $record = new Record($row[$columns['idColumn']], $format);
            if (strlen(trim($row[$columns['contentColumn']])) > 0) {
                $record->setContent($row[$columns['contentColumn']], !$noValidation);
            }
            if (isset($columns['dateColumn'])) {
                $record->setLastChanged(new DateTime($row[$columns['dateColumn']]));
            }
            if (isset($columns['setColumn'])) {
                $sets = $row[$columns['setColumn']];
                foreach (explode(',', trim($sets)) as $set) {
                    /** @var Set */
                    $setSpec = Database::getInstance()->getEntityManager()->getReference(Set::class, $set);
                    $record->addSet($setSpec);
                }
            }
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
     * @return array<string, int|string|null> The mapped column names
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
            $columns[$option] = $headers[$value] ?? null;
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
}
