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

use OCC\OaiPmh2\Configuration;
use OCC\OaiPmh2\Console;
use OCC\OaiPmh2\Entity\Format;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Synchronize metadata formats in database with configuration.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 */
#[AsCommand(
    name: 'oai:formats:update',
    description: 'Update metadata formats in database from configuration'
)]
final class UpdateFormatsCommand extends Console
{
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
        $formats = Configuration::getInstance()->metadataPrefix;
        $this->clearResultCache();
        $inDatabase = $this->em->getMetadataFormats();
        $failure = false;
        foreach ($formats as $prefix => $format) {
            if (
                /** PHPStan and Psalm don't recognize this as assertion. */
                $inDatabase->containsKey($prefix)
                /** @phpstan-ignore-next-line - see above */
                and $format['namespace'] === $inDatabase[$prefix]->getNamespace()
                /** @phpstan-ignore-next-line - see above */
                and $format['schema'] === $inDatabase[$prefix]->getSchema()
            ) {
                continue;
            }
            try {
                $format = new Format($prefix, $format['namespace'], $format['schema']);
                $this->em->addOrUpdate($format);
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
        foreach (array_diff($inDatabase->getKeys(), array_keys($formats)) as $prefix) {
            /** @var Format */
            $format = $inDatabase[$prefix];
            $this->em->delete($format);
            $output->writeln([
                sprintf(
                    ' [OK] Metadata format "%s" and all associated records deleted successfully! ',
                    $prefix
                )
            ]);
        }
        $this->clearResultCache();
        $currentFormats = $this->em->getMetadataFormats()->getKeys();
        if (count($currentFormats) > 0) {
            $output->writeln(
                [
                    '',
                    ' [INFO] The following metadata formats are currently supported: ',
                    ' "' . implode('", "', $currentFormats) . '" ',
                    '',
                    ' To change supported formats edit config/config.yml and run ',
                    ' command "php bin/cli oai:formats:update" again! ',
                    ''
                ],
                OutputInterface::OUTPUT_NORMAL | OutputInterface::VERBOSITY_QUIET
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
                OutputInterface::OUTPUT_NORMAL | OutputInterface::VERBOSITY_QUIET
            );
        }
        return $failure ? Command::FAILURE : Command::SUCCESS;
    }
}
