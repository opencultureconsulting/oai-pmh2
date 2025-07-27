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

namespace OCC\OaiPmh2\Driver;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware as DriverMiddleware;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver as SQLiteDriver;
use Doctrine\DBAL\Driver\SQLite3\Driver as SQLite3Driver;

/**
 * Middleware for Doctrine DBAL drivers.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 */
final class Middleware implements DriverMiddleware
{
    /**
     * Wrap DBAL driver in a decorator.
     *
     * @param Driver $driver The Doctrine DBAL driver
     *
     * @return Driver The wrapped driver
     */
    #[\Override]
    public function wrap(Driver $driver): Driver
    {
        if ($driver instanceof SQLiteDriver || $driver instanceof SQLite3Driver) {
            return new SQLite($driver);
        }
        return $driver;
    }
}
