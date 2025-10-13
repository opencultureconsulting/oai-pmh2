<?php

/**
 * OAI-PMH 2.0 Data Provider
 * Copyright (C) 2025 Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
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

use Doctrine\ORM\Tools\SchemaTool;
use OCC\OaiPmh2\Console;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Upgrade or initialize the database.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 *
 * @extends Console<array{
 *     setSpec: non-empty-string,
 *     setName: non-empty-string,
 *     file?: non-empty-string
 * }>
 */
#[AsCommand(
    name: 'app:upgrade:db',
    description: 'Upgrade or initialize the database',
    aliases: ['app:install:db']
)]
final class UpgradeDatabaseCommand extends Console
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
        if ($input->isInteractive()) {
            $this->io->writeln([
                '<comment>You are about to perform an automated database migration which can lead to data loss.</comment>',
                '<comment>Always keep a BACKUP!</comment>'
            ]);
        }
        if ($this->io->confirm('Continue?', true)) {
            $this->clearAllCaches();
            if (PHP_VERSION_ID < 80400) {
                $this->generateProxies();
            }
            $this->updateSchema();
            $this->io->success('Database successfully upgraded!');
            return Command::SUCCESS;
        } else {
            $this->io->getErrorStyle()->error('Aborted.');
            return Command::FAILURE;
        }
    }

    /**
     * Generates the proxy classes of the Doctrine entities.
     *
     * @return void
     *
     * @deprecated Only necessary if using PHP < 8.4 and Doctrine ORM < 4.0
     */
    protected function generateProxies(): void
    {
        /** @var Application */
        $app = $this->getApplication();
        $app->doRun(
            new ArrayInput([
                'command' => 'orm:generate-proxies'
            ]),
            new NullOutput()
        );
    }

    /**
     * Updates the database schema.
     *
     * @return void
     */
    protected function updateSchema(): void
    {
        $tool = new SchemaTool($this->em);
        $classes = array(
            $this->em->getClassMetadata('OCC\OaiPmh2\Entity\Format'),
            $this->em->getClassMetadata('OCC\OaiPmh2\Entity\Record'),
            $this->em->getClassMetadata('OCC\OaiPmh2\Entity\Set'),
            $this->em->getClassMetadata('OCC\OaiPmh2\Entity\Token')
        );
        $tool->updateSchema($classes);
    }
}
