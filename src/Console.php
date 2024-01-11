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

namespace OCC\OaiPmh2;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base class for all OAI-PMH console commands.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package opencultureconsulting/oai-pmh2
 */
abstract class Console extends Command
{
    /**
     * Clears the result cache.
     *
     * @return void
     */
    protected function clearResultCache(): void
    {
        /** @var Application */
        $app = $this->getApplication();
        $app->doRun(
            new ArrayInput([
                'command' => 'orm:clear-cache:result',
                '--flush' => true
            ]),
            new NullOutput()
        );
    }

    /**
     * Gets the PHP memory limit in bytes.
     *
     * @return int The memory limit in bytes or -1 if unlimited
     */
    protected function getMemoryLimit(): int
    {
        $ini = trim(ini_get('memory_limit'));
        $limit = (int) $ini;
        if ($limit < 0) {
            return -1;
        }
        $unit = strtolower($ini[strlen($ini)-1]);
        switch($unit) {
            case 'g':
                $limit *= 1024;
            case 'm':
                $limit *= 1024;
            case 'k':
                $limit *= 1024;
        }
        return $limit;
    }

    /**
     * Validate input arguments.
     *
     * @param InputInterface $input The inputs
     * @param OutputInterface $output The output interface
     *
     * @return bool Whether the inputs validate
     */
    protected function validateInput(InputInterface $input, OutputInterface $output): bool
    {
        /** @var array<string, string> */
        $arguments = $input->getArguments();

        $formats = Database::getInstance()->getMetadataFormats()->getQueryResult();
        if (!in_array($arguments['format'], array_keys($formats), true)) {
            $output->writeln([
                '',
                sprintf(
                    ' [ERROR] Metadata format "%s" is not supported. ',
                    $arguments['format']
                ),
                ''
            ]);
            return false;
        }
        if (!is_readable($arguments['file'])) {
            $output->writeln([
                '',
                sprintf(
                    ' [ERROR] File "%s" not found or not readable. ',
                    $arguments['file']
                ),
                ''
            ]);
            return false;
        }
        return true;
    }
}
