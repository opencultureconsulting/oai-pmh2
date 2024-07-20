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

use GuzzleHttp\Psr7\Utils;
use OCC\OaiPmh2\Middleware\ErrorHandler;
use OCC\PSR15\AbstractMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Base class for all OAI-PMH requests.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 */
abstract class Middleware extends AbstractMiddleware
{
    /**
     * This holds the prepared response document.
     */
    protected Document $preparedResponse;

    /**
     * Prepare response document.
     *
     * @param ServerRequestInterface $request The pre-processed request
     *
     * @return void
     */
    abstract protected function prepareResponse(ServerRequestInterface $request): void;

    /**
     * Process an incoming server request.
     *
     * @param ServerRequestInterface $request The incoming server request
     *
     * @return ServerRequestInterface The processed server request
     */
    protected function processRequest(ServerRequestInterface $request): ServerRequestInterface
    {
        $this->prepareResponse($request);
        return $request;
    }

    /**
     * Process an incoming response before.
     *
     * @param ResponseInterface $response The incoming response
     *
     * @return ResponseInterface The processed response
     */
    protected function processResponse(ResponseInterface $response): ResponseInterface
    {
        if (!ErrorHandler::getInstance()->hasErrors() && isset($this->preparedResponse)) {
            $response = $response->withBody(Utils::streamFor((string) $this->preparedResponse));
        }
        return $response;
    }

    /**
     * The constructor must have the same signature for all derived classes, thus make it final.
     */
    final public function __construct()
    {
        // Make constructor final to avoid issues in dispatcher.
        // @see https://psalm.dev/229
    }

}
