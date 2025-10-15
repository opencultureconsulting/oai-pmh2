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

use DOMElement;
use GuzzleHttp\Psr7\Utils;
use OCC\OaiPmh2\Entity\Token;
use OCC\OaiPmh2\Middleware\Dispatcher;
use OCC\OaiPmh2\Middleware\ErrorHandler;
use OCC\PSR15\AbstractMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Base class for all OAI-PMH requests.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 *
 * @template RequestParameters of array{
 *     verb: 'Identify'|'GetRecord'|'ListIdentifiers'|'ListMetadataFormats'|'ListRecords'|'ListSets',
 *     identifier?: non-empty-string,
 *     metadataPrefix?: non-empty-string,
 *     from?: non-empty-string,
 *     until?: non-empty-string,
 *     set?: non-empty-string,
 *     resumptionToken?: non-empty-string
 * }
 */
abstract class Middleware extends AbstractMiddleware
{
    /**
     * This holds the request parameters.
     *
     * @var RequestParameters
     */
    protected array $arguments;

    /**
     * This holds the error handler singleton.
     */
    protected ErrorHandler $errorHandler;

    /**
     * This holds the entity manager singleton.
     */
    protected EntityManager $em;

    /**
     * This holds the flow control data.
     *
     * @var array{
     *     counter: non-negative-int,
     *     completeListSize: non-negative-int
     * }
     */
    protected array $flowControl = [
        'counter' => 0,
        'completeListSize' => 0
    ];

    /**
     * This holds the prepared response document.
     */
    protected Response $preparedResponse;

    /**
     * Add resumption token information to response document.
     *
     * @param DOMElement $node The DOM node to add the resumption token to
     * @param ?Token $token The new resumption token or NULL if none
     *
     * @return void
     */
    protected function addResumptionToken(DOMElement $node, ?Token $token): void
    {
        if (isset($token) || isset($this->arguments['resumptionToken'])) {
            $resumptionToken = $this->preparedResponse->createElement('resumptionToken');
            if (isset($token)) {
                $resumptionToken->nodeValue = $token->getToken();
                $resumptionToken->setAttribute(
                    'expirationDate',
                    $token->getValidUntil()->format('Y-m-d\TH:i:s\Z')
                );
                $this->flowControl['completeListSize'] = $token->getParameters()['completeListSize'];
            }
            $resumptionToken->setAttribute(
                'completeListSize',
                (string) $this->flowControl['completeListSize']
            );
            $resumptionToken->setAttribute(
                'cursor',
                (string) ($this->flowControl['counter'] * Configuration::getInstance()->maxRecords)
            );
            $node->appendChild($resumptionToken);
        }
    }

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
    #[\Override]
    protected function processRequest(ServerRequestInterface $request): ServerRequestInterface
    {
        /** @var RequestParameters */
        $arguments = $request->getAttributes();
        $this->arguments = $arguments;
        if ($this->validateArguments()) {
            $this->prepareResponse($request);
        }
        return $request;
    }

    /**
     * Process an incoming response before.
     *
     * @param ResponseInterface $response The incoming response
     *
     * @return ResponseInterface The processed response
     */
    #[\Override]
    protected function processResponse(ResponseInterface $response): ResponseInterface
    {
        if (!$this->errorHandler->hasErrors() && isset($this->preparedResponse)) {
            $response = $response->withBody(Utils::streamFor((string) $this->preparedResponse));
        }
        return $response;
    }

    /**
     * Validate the request arguments.
     *
     * @see https://openarchives.org/OAI/openarchivesprotocol.html#ProtocolMessages
     *
     * @return bool Whether the arguments are a valid set of OAI-PMH request parameters
     *
     * @phpstan-assert-if-true RequestParameters $this->arguments
     */
    abstract protected function validateArguments(): bool;

    /**
     * Validate date/time arguments.
     *
     * @return void
     */
    protected function validateDateTime(): void
    {
        if (
            array_key_exists('from', $this->arguments)
            and array_key_exists('until', $this->arguments)
            and strlen($this->arguments['from']) !== strlen($this->arguments['until'])
        ) {
            $this->errorHandler->withError('badArgument');
            return;
        }
        $from = date_create($this->arguments['from'] ?? $this->em->getEarliestDatestamp());
        $until = date_create($this->arguments['until'] ?? 'NOW');
        if ($from === false || $until === false || $from > $until) {
            $this->errorHandler->withError('badArgument');
        }
    }

    /**
     * Validate "metadataPrefix" argument.
     *
     * @return void
     */
    protected function validateMetadataPrefix(): void
    {
        if (!$this->em->getMetadataFormats()->containsKey($this->arguments['metadataPrefix'] ?? '')) {
            $this->errorHandler->withError('cannotDisseminateFormat');
        }
    }

    /**
     * Check the resumption token and populate request arguments.
     *
     * @return void
     */
    protected function validateResumptionToken(): void
    {
        if (array_key_exists('resumptionToken', $this->arguments)) {
            if (count($this->arguments) !== 2) {
                $this->errorHandler->withError('badArgument');
            }
            $token = $this->em->getResumptionToken($this->arguments['resumptionToken'], $this->arguments['verb']);
            if (isset($token)) {
                foreach ($token->getParameters() as $parameter => $value) {
                    if (in_array($parameter, Dispatcher::OAI_PARAMS, true)) {
                        /** @psalm-suppress InvalidPropertyAssignmentValue */
                        /** @phpstan-ignore assign.propertyType */
                        $this->arguments[$parameter] = $value;
                    } elseif (in_array($parameter, ['counter', 'completeListSize'], true)) {
                        /** @psalm-suppress PropertyTypeCoercion */
                        /** @phpstan-ignore assign.propertyType */
                        $this->flowControl[$parameter] = $value;
                    }
                }
            } else {
                $this->errorHandler->withError('badResumptionToken');
            }
        }
    }

    /**
     * Validate "set" argument.
     *
     * @return void
     */
    protected function validateSet(): void
    {
        if ($this->em->getSets()->isEmpty()) {
            $this->errorHandler->withError('noSetHierarchy');
        }
    }

    /**
     * The constructor must have the same signature for all derived classes, thus make it final.
     *
     * @see https://psalm.dev/229
     */
    final public function __construct()
    {
        $this->em = EntityManager::getInstance();
        $this->errorHandler = ErrorHandler::getInstance();
    }
}
