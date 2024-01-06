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
use OCC\OaiPmh2\Database;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Prune deleted records from database.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package opencultureconsulting/oai-pmh2
 */
#[AsCommand(
    name: 'oai:records:prune',
    description: 'Prune deleted records from database'
)]
class PruneRecordsCommand extends Command
{
    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Deletes records even under "transient" policy.'
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
        $policy = Configuration::getInstance()->deletedRecords;
        $forced = (bool) $input->getOption('force');
        if (
            $policy === 'no'
            or ($policy === 'transient' && $forced)
        ) {
            $deleted = Database::getInstance()->pruneDeletedRecords();
            $output->writeln([
                '',
                sprintf(
                    ' [OK] %d records are deleted and were successfully removed! ',
                    $deleted
                ),
                ''
            ]);
            return Command::SUCCESS;
        } else {
            if ($policy === 'persistent') {
                $output->writeln([
                    '',
                    ' [ERROR] Under "persistent" policy removal of deleted records is not allowed. ',
                    ''
                ]);
                return Command::FAILURE;
            } else {
                $output->writeln([
                    '',
                    ' [INFO] Use the "--force" option to remove deleted records under "transient" policy. ',
                    ''
                ]);
                return Command::INVALID;
            }
        }
    }
}
