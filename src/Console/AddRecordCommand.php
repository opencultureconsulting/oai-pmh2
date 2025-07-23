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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Add or update a record in the database.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 */
#[AsCommand(
    name: 'oai:add:record',
    description: 'Add or update a record in the database',
    aliases: ['oai:update:record']
)]
final class AddRecordCommand extends Console
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
            'identifier',
            InputArgument::REQUIRED,
            'The record identifier'
        );
        $this->addArgument(
            'format',
            InputArgument::REQUIRED,
            'The metadata prefix'
        );
        $this->addArgument(
            'file',
            InputArgument::REQUIRED,
            'The file containing the record content'
        );
        $this->addArgument(
            'sets',
            InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
            'The list of sets to associate the record with'
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

        $this->addOrUpdateRecord(
            $this->arguments['identifier'],
            trim((string) file_get_contents($this->arguments['file'])) ?: null,
            new DateTime(),
            $this->arguments['sets'] ?? []
        );
        $this->em->pruneOrphanedSets();
        $this->clearResultCache();

        $this->io->success(
            sprintf(
                'Record "%s" with metadata prefix "%s" added or updated successfully!',
                $this->arguments['identifier'],
                $this->arguments['format']
            )
        );
        return Command::SUCCESS;
    }
}
