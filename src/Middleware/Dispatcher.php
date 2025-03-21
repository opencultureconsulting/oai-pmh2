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

use OCC\OaiPmh2\EntityManager;
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
final class Dispatcher extends AbstractMiddleware
{
    /**
     * List of defined OAI-PMH parameters.
     *
     * @var string[]
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
    #[\Override]
    protected function processRequest(ServerRequestInterface $request): ServerRequestInterface
    {
        $request = $this->getRequestWithAttributes($request);
        $errorHandler = ErrorHandler::getInstance();
        if (!$errorHandler->hasErrors()) {
            /** @var string */
            $verb = $request->getAttribute('verb');
            $middleware = __NAMESPACE__ . '\\' . $verb;
            if (is_a($middleware, Middleware::class, true)) {
                $this->requestHandler->queue->enqueue(new $middleware());
            }
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
    #[\Override]
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
            match ($arguments['verb']) {
                'GetRecord' => $this->validateGetRecord($arguments),
                'Identify' => $this->validateIdentify($arguments),
                'ListIdentifiers', 'ListRecords' => $this->validateListRecords($arguments),
                'ListMetadataFormats' => $this->validateListFormats($arguments),
                'ListSets' => $this->validateListSets($arguments),
                default => ErrorHandler::getInstance()->withError('badVerb')
            };
            if (!ErrorHandler::getInstance()->hasErrors()) {
                $this->validateMetadataPrefix($arguments['metadataPrefix'] ?? null);
                $this->validateDateTime($arguments['from'] ?? null);
                $this->validateDateTime($arguments['until'] ?? null);
                $this->validateSet($arguments['set'] ?? null);
            }
        }
        return !ErrorHandler::getInstance()->hasErrors();
    }

    /**
     * Validate "from" and "until" argument.
     *
     * @param ?string $datetime The datetime string to validate or NULL if none
     *
     * @return void
     */
    protected function validateDateTime(?string $datetime): void
    {
        if (isset($datetime)) {
            $date = date_parse($datetime);
            if ($date['warning_count'] > 0 || $date['error_count'] > 0) {
                ErrorHandler::getInstance()->withError('badArgument');
            }
        }
    }

    /**
     * Validate request arguments for verb GetRecord.
     *
     * @param string[] $arguments The request parameters
     *
     * @return void
     */
    protected function validateGetRecord(array $arguments): void
    {
        if (
            count($arguments) !== 3
            or !isset($arguments['identifier'])
            or !isset($arguments['metadataPrefix'])
        ) {
            ErrorHandler::getInstance()->withError('badArgument');
        }
    }

    /**
     * Validate request arguments for verb Identify.
     *
     * @param string[] $arguments The request parameters
     *
     * @return void
     */
    protected function validateIdentify(array $arguments): void
    {
        if (count($arguments) !== 1) {
            ErrorHandler::getInstance()->withError('badArgument');
        }
    }

    /**
     * Validate request arguments for verb ListMetadataFormats.
     *
     * @param string[] $arguments The request parameters
     *
     * @return void
     */
    protected function validateListFormats(array $arguments): void
    {
        if (count($arguments) !== 1) {
            if (!isset($arguments['identifier']) || count($arguments) !== 2) {
                ErrorHandler::getInstance()->withError('badArgument');
            }
        }
    }

    /**
     * Validate request arguments for verbs ListIdentifiers and ListRecords.
     *
     * @param string[] $arguments The request parameters
     *
     * @return void
     */
    protected function validateListRecords(array $arguments): void
    {
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
    }

    /**
     * Validate request arguments for verb ListSets.
     *
     * @param string[] $arguments The request parameters
     *
     * @return void
     */
    protected function validateListSets(array $arguments): void
    {
        if (count($arguments) !== 1) {
            if (!isset($arguments['resumptionToken']) || count($arguments) !== 2) {
                ErrorHandler::getInstance()->withError('badArgument');
            }
        }
    }

    /**
     * Validate "metadataPrefix" argument.
     *
     * @param ?string $prefix The metadata prefix
     *
     * @return void
     */
    protected function validateMetadataPrefix(?string $prefix): void
    {
        if (isset($prefix)) {
            $formats = EntityManager::getInstance()->getMetadataFormats();
            if (!$formats->containsKey($prefix)) {
                ErrorHandler::getInstance()->withError('cannotDisseminateFormat');
            }
        }
    }

    /**
     * Validate "set" argument.
     *
     * @param ?string $spec The set spec
     *
     * @return void
     */
    protected function validateSet(?string $spec): void
    {
        if (isset($spec)) {
            $sets = EntityManager::getInstance()->getSets();
            if (!$sets->containsKey($spec)) {
                ErrorHandler::getInstance()->withError('badArgument');
            }
        }
    }
}
