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

use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use Doctrine\Migrations\Configuration\Migration\ExistingConfiguration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use Doctrine\Migrations\Tools\Console\Command\DiffCommand;
use Doctrine\Migrations\Tools\Console\Command\MigrateCommand;
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
            $dependencyFactory = $this->getDependencyFactory();
            /** @var Application */
            $app = $this->getApplication();
            $app->addCommands([
                new DiffCommand($dependencyFactory, 'orm:schema:diff'),
                new MigrateCommand($dependencyFactory, 'orm:schema:migrate'),
            ]);
            $output = new NullOutput();
            $input = new ArrayInput([
                'command' => 'orm:schema:diff',
                '--allow-empty-diff' => true
            ]);
            $errorCode = $app->doRun($input, $output);
            if ($errorCode === 0) {
                $input = new ArrayInput([
                    'command' => 'orm:schema:migrate',
                    '--allow-no-migration' => true,
                    '--no-interaction' => true
                ]);
                $input->setInteractive(false);
                $errorCode = $app->doRun($input, $output);
            }
            if ($errorCode === 0) {
                $this->io->success('Database successfully upgraded!');
            } else {
                $this->io->getErrorStyle()->error('Failed to upgrade database.');
            }
            return $errorCode;
        } else {
            $this->io->getErrorStyle()->error('Aborted.');
            return Command::FAILURE;
        }
    }

    /**
     * Gets the dependency factory for migration commands.
     *
     * @return DependencyFactory The dependency factory
     */
    protected function getDependencyFactory(): DependencyFactory
    {
        $storage = new TableMetadataStorageConfiguration();
        $storage->setTableName('migrations');
        $configuration = new Configuration();
        $configuration->addMigrationsDirectory(
            'OCC\\OaiPmh2\\Migration',
            __DIR__ . '/../../data/migrations'
        );
        $configuration->setCheckDatabasePlatform(true);
        $configuration->setCustomTemplate(__DIR__ . '/../Migration.template');
        $configuration->setMetadataStorageConfiguration($storage);
        $configuration->setMigrationOrganization(Configuration::VERSIONS_ORGANIZATION_NONE);
        $configuration->setTransactional(true);
        return DependencyFactory::fromEntityManager(
            new ExistingConfiguration($configuration),
            new ExistingEntityManager($this->em)
        );
    }
}
