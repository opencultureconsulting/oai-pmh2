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

use OCC\OaiPmh2\Console;
use OCC\OaiPmh2\Database;
use OCC\OaiPmh2\Entity\Format;
use OCC\OaiPmh2\Entity\Record;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Delete a record from database.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package opencultureconsulting/oai-pmh2
 */
#[AsCommand(
    name: 'oai:records:delete',
    description: 'Delete a record from database'
)]
class DeleteRecordCommand extends Console
{
    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->addArgument(
            'identifier',
            InputArgument::REQUIRED,
            'The record identifier.'
        );
        $this->addArgument(
            'format',
            InputArgument::REQUIRED,
            'The metadata prefix.'
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
        $entityManager = Database::getInstance()->getEntityManager();

        $format = $entityManager->getReference(Format::class, $arguments['format']);
        $record = $entityManager->find(
            Record::class,
            [
                'identifier' => $arguments['identifier'],
                'format' => $format
            ]
        );

        if (isset($record)) {
            Database::getInstance()->deleteRecord($record);
            $this->clearResultCache();
            $output->writeln([
                '',
                sprintf(
                    ' [OK] Record "%s" with metadata prefix "%s" successfully deleted. ',
                    $arguments['identifier'],
                    $arguments['format']
                ),
                ''
            ]);
            return Command::SUCCESS;
        } else {
            $output->writeln([
                '',
                sprintf(
                    ' [ERROR] Record "%s" with metadata prefix "%s" not found. ',
                    $arguments['identifier'],
                    $arguments['format']
                ),
                ''
            ]);
            return Command::FAILURE;
        }
    }
}
