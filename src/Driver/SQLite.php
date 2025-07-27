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

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Exception;

/**
 * Decorator for Doctrine DBAL SQLite drivers.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 *
 * @psalm-import-type Params from DriverManager
 */
final class SQLite extends AbstractDriverMiddleware
{
    /**
     * Attempt to create a connection with the database.
     *
     * @psalm-suppress MoreSpecificImplementedParamType
     * @param Params $params All connection parameters
     *
     * @return Connection The database connection
     *
     * @throws Exception if connection can not be established
     */
    #[\Override]
    public function connect(
        #[\SensitiveParameter]
        array $params,
    ): Connection {
        $connection = parent::connect($params);
        // Set busy timeout to 5 seconds to gracefully handle concurrent access
        $connection->exec('PRAGMA busy_timeout = 5000');
        return $connection;
    }
}
