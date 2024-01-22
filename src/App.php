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

use OCC\OaiPmh2\Middleware\Dispatcher;
use OCC\PSR15\QueueRequestHandler;

/**
 * Main application of the OAI-PMH 2.0 Data Provider.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package opencultureconsulting/oai-pmh2
 */
class App
{
    /**
     * The PSR-15 Server Request Handler.
     */
    protected QueueRequestHandler $requestHandler;

    /**
     * Instantiate application.
     */
    public function __construct()
    {
        $this->requestHandler = new QueueRequestHandler([new Dispatcher()]);
    }

    /**
     * Run the application.
     *
     * @return void
     */
    public function run(): void
    {
        $this->requestHandler->handle();
        $this->requestHandler->respond();
    }
}
