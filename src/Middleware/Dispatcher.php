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

use LogicException;
use OCC\PSR15\AbstractMiddleware;
use OCC\PSR15\QueueRequestHandler;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Validate and dispatch a OAI-PMH server request.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 */
final class Dispatcher extends AbstractMiddleware
{
    /**
     * List of defined OAI-PMH parameters.
     */
    public const OAI_PARAMS = [
        'verb',
        'identifier',
        'metadataPrefix',
        'from',
        'until',
        'set',
        'resumptionToken'
    ];

    /**
     * Map of defined OAI-PMH verbs and associated middlewares.
     */
    public const OAI_VERBS = [
        'Identify' => Identify::class,
        'GetRecord' => GetRecord::class,
        'ListIdentifiers' => ListIdentifiers::class,
        'ListMetadataFormats' => ListMetadataFormats::class,
        'ListRecords' => ListRecords::class,
        'ListSets' => ListSets::class
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
        $query = '';
        if ($request->getMethod() === 'GET') {
            $query = $request->getUri()->getQuery();
        } elseif ($request->getMethod() === 'POST' && $this->isFormUrlEncoded($request)) {
            $query = (string) $request->getBody();
        }
        $arguments = $this->parseArguments($query);
        if ($this->validateArguments($arguments)) {
            foreach ($arguments as $param => $values) {
                $request = $request->withAttribute((string) $param, $values[0]);
            }
        }
        return $request;
    }

    /**
     * Check if the request body is form-urlencoded, ignoring parameters like "charset".
     *
     * @param ServerRequestInterface $request The POST request
     *
     * @return bool Whether the request body is form-urlencoded
     */
    protected function isFormUrlEncoded(ServerRequestInterface $request): bool
    {
        $mediaType = explode(';', $request->getHeaderLine('Content-Type'), 2)[0];
        return strtolower(trim($mediaType)) === 'application/x-www-form-urlencoded';
    }

    /**
     * Parse a raw query string or form-urlencoded body.
     *
     * PHP's own parser silently keeps only the last value of a repeated argument,
     * so parse the raw string to be able to detect repeated arguments.
     *
     * @param string $query The raw query string or request body
     *
     * @return array<array-key, non-empty-list<string>> All values of each argument
     */
    protected function parseArguments(string $query): array
    {
        $arguments = [];
        foreach (explode('&', $query) as $pair) {
            if ($pair === '') {
                continue;
            }
            $parts = explode('=', $pair, 2);
            $arguments[urldecode($parts[0])][] = urldecode($parts[1] ?? '');
        }
        return $arguments;
    }

    /**
     * Dispatch the OAI-PMH request.
     *
     * @param ServerRequestInterface $request The request to dispatch
     *
     * @return ServerRequestInterface The processed server request
     *
     * @throws LogicException if the request handler is not a queue request handler
     */
    #[Override]
    protected function processRequest(ServerRequestInterface $request): ServerRequestInterface
    {
        if (!$this->requestHandler instanceof QueueRequestHandler) {
            throw new LogicException(
                sprintf('The dispatcher requires a %s to enqueue the OAI-PMH middlewares.', QueueRequestHandler::class)
            );
        }
        $request = $this->getRequestWithAttributes($request);
        $errorHandler = ErrorHandler::getInstance();
        if (!$errorHandler->hasErrors()) {
            /** @var key-of<self::OAI_VERBS> */
            $verb = $request->getAttribute('verb');
            $middleware = self::OAI_VERBS[$verb];
            $this->requestHandler->queue->enqueue(new $middleware());
        }
        $this->requestHandler->queue->enqueue($errorHandler);
        return $request;
    }

    /**
     * Finalize the OAI-PMH response.
     *
     * @param ResponseInterface $response The response to finalize
     *
     * @return ResponseInterface The final response
     */
    #[Override]
    protected function processResponse(ResponseInterface $response): ResponseInterface
    {
        // TODO: Add support for content compression
        // https://openarchives.org/OAI/openarchivesprotocol.html#ResponseCompression
        // https://github.com/middlewares/encoder
        return $response->withHeader('Content-Type', 'text/xml');
    }

    /**
     * Validate the request parameters.
     *
     * @see https://openarchives.org/OAI/openarchivesprotocol.html#ProtocolMessages
     *
     * @param array<array-key, non-empty-list<string>> $arguments All values of each request parameter
     *
     * @return bool Whether the parameters are valid
     */
    protected function validateArguments(array $arguments): bool
    {
        if (
            !array_key_exists('verb', $arguments)
            or count($arguments['verb']) !== 1
            or !in_array($arguments['verb'][0], array_keys(self::OAI_VERBS), true)
        ) {
            ErrorHandler::getInstance()->withError('badVerb');
        } elseif (
            count(array_diff(array_keys($arguments), self::OAI_PARAMS)) !== 0
            or count(array_filter($arguments, fn (array $values): bool => count($values) > 1)) !== 0
        ) {
            ErrorHandler::getInstance()->withError('badArgument');
        }
        return !ErrorHandler::getInstance()->hasErrors();
    }
}
