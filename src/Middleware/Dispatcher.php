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

namespace OCC\OaiPmh2\Middleware;

use OCC\OaiPmh2\Middleware;
use OCC\PSR15\AbstractMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Validate and dispatch a OAI-PMH server request.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 */
class Dispatcher extends AbstractMiddleware
{
    /**
     * List of defined OAI-PMH parameters.
     */
    protected const OAI_PARAMS = [
        'verb',
        'identifier',
        'metadataPrefix',
        'from',
        'until',
        'set',
        'resumptionToken'
    ];

    /**
     * Get server request populated with request attributes.
     *
     * @param ServerRequestInterface $request The GET or POST request
     *
     * @return ServerRequestInterface The same request with parsed attributes
     */
    protected function getRequestWithAttributes(ServerRequestInterface $request): ServerRequestInterface
    {
        $arguments = [];
        if ($request->getMethod() === 'GET') {
            /** @var array<string, string> */
            $arguments = $request->getQueryParams();
        } elseif ($request->getMethod() === 'POST') {
            if ($request->getHeaderLine('Content-Type') === 'application/x-www-form-urlencoded') {
                /** @var array<string, string> */
                $arguments = (array) $request->getParsedBody();
            }
        }
        if ($this->validateArguments($arguments)) {
            foreach ($arguments as $param => $value) {
                $request = $request->withAttribute($param, $value);
            }
        }
        return $request;
    }

    /**
     * Dispatch the OAI-PMH request.
     *
     * @param ServerRequestInterface $request The request to dispatch
     *
     * @return ServerRequestInterface The processed server request
     */
    protected function processRequest(ServerRequestInterface $request): ServerRequestInterface
    {
        $request = $this->getRequestWithAttributes($request);
        if (!ErrorHandler::getInstance()->hasErrors()) {
            /** @var string */
            $verb = $request->getAttribute('verb');
            $middleware = __NAMESPACE__ . '\\' . $verb;
            if (is_a($middleware, Middleware::class, true)) {
                $this->requestHandler->queue->enqueue(new $middleware());
            }
        }
        $this->requestHandler->queue->enqueue(ErrorHandler::getInstance());
        return $request;
    }

    /**
     * Finalize the OAI-PMH response.
     *
     * @param ResponseInterface $response The response to finalize
     *
     * @return ResponseInterface The final response
     */
    protected function processResponse(ResponseInterface $response): ResponseInterface
    {
        // TODO: Add support for content compression
        // https://openarchives.org/OAI/openarchivesprotocol.html#ResponseCompression
        // https://github.com/middlewares/encoder
        return $response->withHeader('Content-Type', 'text/xml');
    }

    /**
     * Validate the request parameters.
     * @see https://openarchives.org/OAI/openarchivesprotocol.html#ProtocolMessages
     *
     * @param string[] $arguments The request parameters
     *
     * @return bool Whether the parameters are syntactically valid
     */
    protected function validateArguments(array $arguments): bool
    {
        if (
            count(array_diff(array_keys($arguments), self::OAI_PARAMS)) !== 0
            or !isset($arguments['verb'])
        ) {
            ErrorHandler::getInstance()->withError('badArgument');
        } else {
            switch ($arguments['verb']) {
                case 'GetRecord':
                    if (
                        count($arguments) !== 3
                        or !isset($arguments['identifier'])
                        or !isset($arguments['metadataPrefix'])
                    ) {
                        ErrorHandler::getInstance()->withError('badArgument');
                    }
                    break;
                case 'Identify':
                    if (count($arguments) !== 1) {
                        ErrorHandler::getInstance()->withError('badArgument');
                    }
                    break;
                case 'ListIdentifiers':
                case 'ListRecords':
                    if (
                        isset($arguments['metadataPrefix'])
                        xor isset($arguments['resumptionToken'])
                    ) {
                        if (
                            (isset($arguments['resumptionToken']) && count($arguments) !== 2)
                            or isset($arguments['identifier'])
                        ) {
                            ErrorHandler::getInstance()->withError('badArgument');
                        }
                    } else {
                        ErrorHandler::getInstance()->withError('badArgument');
                    }
                    break;
                case 'ListMetadataFormats':
                    if (count($arguments) !== 1) {
                        if (!isset($arguments['identifier']) || count($arguments) !== 2) {
                            ErrorHandler::getInstance()->withError('badArgument');
                        }
                    }
                    break;
                case 'ListSets':
                    if (count($arguments) !== 1) {
                        if (!isset($arguments['resumptionToken']) || count($arguments) !== 2) {
                            ErrorHandler::getInstance()->withError('badArgument');
                        }
                    }
                    break;
                default:
                    ErrorHandler::getInstance()->withError('badVerb');
            }
        }
        return !ErrorHandler::getInstance()->hasErrors();
    }
}
