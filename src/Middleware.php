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
 * @phpstan-type OaiRequestMetadata = array{
 *     verb: 'Identify'|'GetRecord'|'ListIdentifiers'|'ListMetadataFormats'|'ListRecords'|'ListSets',
 *     identifier: ?non-empty-string,
 *     metadataPrefix: ?non-empty-string,
 *     from: ?non-empty-string,
 *     until: ?non-empty-string,
 *     set: ?non-empty-string,
 *     resumptionToken: ?non-empty-string,
 *     counter: non-negative-int,
 *     completeListSize: non-negative-int
 * }
 */
abstract class Middleware extends AbstractMiddleware
{
    /**
     * This holds the request metadata.
     *
     * @var OaiRequestMetadata
     */
    protected array $arguments = [
        'verb' => 'Identify',
        'identifier' => null,
        'metadataPrefix' => null,
        'from' => null,
        'until' => null,
        'set' => null,
        'resumptionToken' => null,
        'counter' => 0,
        'completeListSize' => 0
    ];

    /**
     * This holds the entity manager singleton.
     */
    protected EntityManager $em;

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
                $this->arguments['completeListSize'] = $token->getParameters()['completeListSize'];
            }
            $resumptionToken->setAttribute(
                'completeListSize',
                (string) $this->arguments['completeListSize']
            );
            $resumptionToken->setAttribute(
                'cursor',
                (string) ($this->arguments['counter'] * Configuration::getInstance()->maxRecords)
            );
            $node->appendChild($resumptionToken);
        }
    }

    /**
     * Check for resumption token and populate request arguments.
     *
     * @return void
     */
    protected function checkResumptionToken(): void
    {
        if (isset($this->arguments['resumptionToken'])) {
            $token = $this->em->getResumptionToken($this->arguments['resumptionToken'], $this->arguments['verb']);
            if (isset($token)) {
                $this->arguments = array_merge($this->arguments, $token->getParameters());
            } else {
                ErrorHandler::getInstance()->withError('badResumptionToken');
            }
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
        /** @var OaiRequestMetadata */
        $arguments = $request->getAttributes();
        $this->arguments = array_merge($this->arguments, $arguments);
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
    #[\Override]
    protected function processResponse(ResponseInterface $response): ResponseInterface
    {
        if (!ErrorHandler::getInstance()->hasErrors() && isset($this->preparedResponse)) {
            $response = $response->withBody(Utils::streamFor((string) $this->preparedResponse));
        }
        return $response;
    }

    /**
     * The constructor must have the same signature for all derived classes, thus make it final.
     *
     * @see https://psalm.dev/229
     */
    final public function __construct()
    {
        $this->em = EntityManager::getInstance();
    }
}
