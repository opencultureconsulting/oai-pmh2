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

use OCC\OaiPmh2\Configuration;
use OCC\OaiPmh2\Console;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Switch maintenance mode on or off.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 *
 * @extends Console<array{
 *     switch: 'on'|'off'|null
 * }>
 */
#[AsCommand(
    name: 'app:maintenance:mode',
    description: 'Show maintenance mode status and turn it on or off',
)]
final class MaintenanceModeCommand extends Console
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
            'switch',
            InputArgument::OPTIONAL,
            '"on" or "off"'
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
        match ($input->getArgument('switch')) {
            'on', 'true', '1' => Configuration::getInstance()->setMaintenanceMode(true),
            'off', 'false', '0' => Configuration::getInstance()->setMaintenanceMode(false),
            default => null,
        };
        $this->io->success(
            sprintf(
                'Maintenance mode is %s.',
                Configuration::getInstance()->maintenanceMode ? 'on' : 'off'
            )
        );
        return Command::SUCCESS;
    }
}
