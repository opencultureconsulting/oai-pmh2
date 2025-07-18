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
 * @package OAIPMH2
 *
 * @psalm-type CliArguments = array{
 *     identifier: string,
 *     format: string,
 *     file: string,
 *     sets?: list<string>,
 *     setSpec: string,
 *     setName: string,
 *     idColumn: string,
 *     contentColumn: string,
 *     dateColumn: string,
 *     setColumn: string,
 *     noValidation: bool,
 *     force: bool
 * }
 */
abstract class Console extends Command
{
    /**
     * This holds the command's arguments and options.
     *
     * @var CliArguments
     */
    protected array $arguments;

    /**
     * This holds the entity manager singleton.
     */
    protected readonly EntityManager $em;

    /**
     * This holds the PHP memory limit in bytes.
     */
    protected int $memoryLimit;

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
     * Flush and clear unit of work.
     *
     * @return void
     */
    protected function flushAndClear(): void
    {
        $this->em->flush();
        $this->em->clear();
    }

    /**
     * Gets the PHP memory limit in bytes.
     *
     * @return int The memory limit in bytes or -1 if unlimited
     */
    protected function getPhpMemoryLimit(): int
    {
        if (!isset($this->memoryLimit)) {
            $phpValue = (string) ini_get('memory_limit');
            $limit = (int) $phpValue;
            if ($limit <= 0) {
                $limit = -1;
            } else {
                $unit = strtolower($phpValue[strlen($phpValue) - 1]);
                switch ($unit) {
                    case 'g':
                        $limit *= 1024;
                        // no break
                    case 'm':
                        $limit *= 1024;
                        // no break
                    case 'k':
                        $limit *= 1024;
                }
            }
            $this->memoryLimit = $limit;
        }
        return $this->memoryLimit;
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
        /** @var CliArguments */
        $mergedArguments = array_merge($input->getArguments(), $input->getOptions());
        $this->arguments = $mergedArguments;

        if (array_key_exists('format', $this->arguments)) {
            $formats = $this->em->getMetadataFormats();
            if (!$formats->containsKey($this->arguments['format'])) {
                $output->writeln([
                    '',
                    sprintf(
                        ' [ERROR] Metadata format "%s" is not supported. ',
                        $this->arguments['format']
                    ),
                    ''
                ]);
                return false;
            }
        }
        if (array_key_exists('file', $this->arguments) && !is_readable($this->arguments['file'])) {
            $output->writeln([
                '',
                sprintf(
                    ' [ERROR] File "%s" not found or not readable. ',
                    $this->arguments['file']
                ),
                ''
            ]);
            return false;
        }
        if (array_key_exists('sets', $this->arguments)) {
            $sets = $this->em->getSets();
            $invalidSets = array_diff($this->arguments['sets'], $sets->getKeys());
            if (count($invalidSets) !== 0) {
                $output->writeln([
                    '',
                    sprintf(
                        ' [ERROR] Sets "%s" are not supported. ',
                        implode('", "', $invalidSets)
                    ),
                    ''
                ]);
                return false;
            }
        }
        return true;
    }

    /**
     * Create new console command instance.
     *
     * @param ?string $name The name of the command
     *                      passing null means it must be set in configure()
     */
    public function __construct(?string $name = null)
    {
        $this->em = EntityManager::getInstance();
        parent::__construct($name);
    }
}
