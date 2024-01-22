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

use OCC\OaiPmh2\Console;
use OCC\OaiPmh2\Database;
use OCC\OaiPmh2\Entity\Set;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Add or update a set in the database.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package opencultureconsulting/oai-pmh2
 */
#[AsCommand(
    name: 'oai:sets:add',
    description: 'Add or update a set in the database'
)]
class AddSetCommand extends Console
{
    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->addArgument(
            'setSpec',
            InputArgument::REQUIRED,
            'The set (spec) to update.',
            null,
            function (): array {
                return array_keys(Database::getInstance()->getAllSets()->getQueryResult());
            }
        );
        $this->addArgument(
            'setName',
            InputArgument::REQUIRED,
            'The new set name.'
        );
        $this->addArgument(
            'file',
            InputArgument::OPTIONAL,
            'The optional file containing the set description XML.'
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
        /** @var array<string, string> */
        $arguments = $input->getArguments();
        $description = null;

        if (isset($arguments['file'])) {
            if (!is_readable($arguments['file'])) {
                $output->writeln([
                    '',
                    sprintf(
                        ' [ERROR] File "%s" not found or not readable. ',
                        $arguments['file']
                    ),
                    ''
                ]);
                return Command::INVALID;
            } else {
                $description = (string) file_get_contents($arguments['file']);
            }
        }

        $set = new Set(
            $arguments['setSpec'],
            $arguments['setName'],
            $description
        );
        Database::getInstance()->addOrUpdateSet($set);

        return Command::SUCCESS;
    }
}
