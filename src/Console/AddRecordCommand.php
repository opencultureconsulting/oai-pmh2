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
use OCC\OaiPmh2\Entity\Set;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Add or update a record in the database.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package opencultureconsulting/oai-pmh2
 */
#[AsCommand(
    name: 'oai:records:add',
    description: 'Add or update a record in the database'
)]
class AddRecordCommand extends Console
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
        $this->addArgument(
            'file',
            InputArgument::REQUIRED,
            'The file containing the record content.'
        );
        $this->addArgument(
            'sets',
            InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
            'The list of sets to associate the record with.'
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
        /** @var string */
        $identifier = $input->getArgument('identifier');
        /** @var Format */
        $format = Database::getInstance()->getEntityManager()->getReference(Format::class, $input->getArgument('format'));
        /** @var string */
        $file = $input->getArgument('file');
        /** @var string[] */
        $sets = $input->getArgument('sets');
        /** @var string */
        $content = file_get_contents($file);

        $record = new Record($identifier, $format);
        if (trim($content) !== '') {
            $record->setContent($content);
        }
        foreach ($sets as $set) {
            /** @var Set */
            $setSpec = Database::getInstance()->getEntityManager()->getReference(Set::class, $set);
            $record->addSet($setSpec);
        }

        Database::getInstance()->addOrUpdateRecord($record);
        Database::getInstance()->pruneOrphanSets();

        $this->clearResultCache();

        $output->writeln([
            '',
            sprintf(
                ' [OK] Record "%s" with metadata prefix "%s" added or updated successfully! ',
                $identifier,
                $format->getPrefix()
            ),
            ''
        ]);
        return Command::SUCCESS;
    }
}
