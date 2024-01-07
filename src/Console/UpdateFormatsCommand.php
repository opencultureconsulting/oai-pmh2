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

use OCC\OaiPmh2\Configuration;
use OCC\OaiPmh2\ConsoleCommand;
use OCC\OaiPmh2\Database;
use OCC\OaiPmh2\Database\Format;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Synchronize metadata formats in database with configuration.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package opencultureconsulting/oai-pmh2
 */
#[AsCommand(
    name: 'oai:formats:update',
    description: 'Update metadata formats in database from configuration'
)]
class UpdateFormatsCommand extends ConsoleCommand
{
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
        $formats = Configuration::getInstance()->metadataPrefix;
        $inDatabase = Database::getInstance()->getMetadataFormats()->getQueryResult();
        $added = 0;
        $deleted = 0;
        $failure = false;
        foreach ($formats as $prefix => $format) {
            if (in_array($prefix, array_keys($inDatabase), true)) {
                if (
                    $format['namespace'] === $inDatabase[$prefix]->getNamespace()
                    and $format['schema'] === $inDatabase[$prefix]->getSchema()
                ) {
                    continue;
                }
            }
            try {
                $format = new Format($prefix, $format['namespace'], $format['schema']);
                Database::getInstance()->addOrUpdateMetadataFormat($format);
                ++$added;
                $output->writeln([
                    sprintf(
                        ' [OK] Metadata format "%s" added or updated successfully! ',
                        $prefix
                    )
                ]);
            } catch (ValidationFailedException $exception) {
                $failure = true;
                $output->writeln([
                    sprintf(
                        ' [ERROR] Could not add or update metadata format "%s". ',
                        $prefix
                    ),
                    $exception->getMessage()
                ]);
            }
        }
        foreach (array_keys($inDatabase) as $prefix) {
            if (!in_array($prefix, array_keys($formats), true)) {
                Database::getInstance()->deleteMetadataFormat($inDatabase[$prefix]);
                ++$deleted;
                $output->writeln([
                    sprintf(
                        ' [OK] Metadata format "%s" and all associated records deleted successfully! ',
                        $prefix
                    )
                ]);
            }
        }
        $this->clearResultCache();
        $currentFormats = array_keys(Database::getInstance()->getMetadataFormats()->getQueryResult());
        if (count($currentFormats) > 0) {
            $output->writeln(
                [
                    '',
                    ' The following metadata formats are currently supported: ',
                    ' ======================================================= ',
                    '',
                    '  "' . implode('", "', $currentFormats) . '" ',
                    ''
                ],
                1 | 16
            );
        } else {
            $output->writeln(
                [
                    '',
                    ' [INFO] There are currently no metadata formats supported. ',
                    ' Please add a metadata prefix to config/config.yml and run ',
                    ' command "php bin/cli oai:formats:update" again! ',
                    ''
                ],
                1 | 16
            );
        }
        if (!$failure) {
            return Command::SUCCESS;
        } else {
            return Command::FAILURE;
        }
    }
}
