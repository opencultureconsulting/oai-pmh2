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
use OCC\OaiPmh2\Database;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
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
class CsvImportCommand extends Command
{
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
            null,
            InputOption::VALUE_OPTIONAL,
            'Name of the CSV column which holds the records\' identifier.',
            'identifier'
        );
        $this->addOption(
            'contentColumn',
            null,
            InputOption::VALUE_OPTIONAL,
            'Name of the CSV column which holds the records\' content.',
            'content'
        );
        $this->addOption(
            'dateColumn',
            null,
            InputOption::VALUE_OPTIONAL,
            'Name of the CSV column which holds the records\' datetime of last change.',
            'lastChanged'
        );
        $this->addOption(
            'setColumn',
            null,
            InputOption::VALUE_OPTIONAL,
            'Name of the CSV column which holds the records\' sets list.',
            'sets'
        );
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var array<string, string> */
        $arguments = $input->getArguments();
        /** @var array<string, string> */
        $options = $input->getOptions();

        $formats = Database::getInstance()->getMetadataFormats()->getQueryResult();
        if (!in_array($arguments['format'], array_keys($formats), true)) {
            // Error: Invalid metadata prefix
            echo 1;
            return Command::INVALID;
        }

        $file = fopen($arguments['file'], 'r');
        if ($file === false) {
            // Error: File not found or not readable
            echo 2;
            return Command::INVALID;
        }

        $headers = fgetcsv($file);
        if (!is_array($headers)) {
            // Error: No CSV
            echo 3;
            return Command::INVALID;
        } else {
            $headers = array_flip($headers);
        }

        $column = [];
        foreach ($options as $option => $value) {
            if (isset($headers[$value])) {
                $column[$option] = $headers[$value];
            }
        }
        if (!isset($column['idColumn']) || !isset($column['contentColumn'])) {
            // Error: Required columns missing
            echo 4;
            return Command::INVALID;
        }
        $lastChanged = new DateTime();

        $count = 0;
        while ($record = fgetcsv($file)) {
            $identifier = $record[$column['idColumn']];
            $content = $record[$column['contentColumn']];
            if ($content === '') {
                $content = null;
            }
            if (isset($column['dateColumn'])) {
                $lastChanged = new DateTime($record[$column['dateColumn']]);
            }
            // TODO: Complete support for sets.
            $sets = null;
            Database::getInstance()->addOrUpdateRecord(
                $identifier,
                $arguments['format'],
                $content,
                $lastChanged,
                $sets,
                true
            );
            ++$count;
            if ($count % 500 === 0) {
                Database::getInstance()->flush(true);
            }
        }
        Database::getInstance()->flush(true);
        Database::getInstance()->pruneOrphanSets();

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
}
