<?php

/**
 * OAI-PMH 2.0 Data Provider
 * Copyright (C) 2023 Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace OCC\OaiPmh2\Middleware;

use DomainException;
use GuzzleHttp\Psr7\Utils;
use OCC\Basics\Traits\Singleton;
use OCC\OaiPmh2\Document;
use OCC\PSR15\AbstractMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Handles OAI-PMH errors.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package opencultureconsulting/oai-pmh2
 */
class ErrorHandler extends AbstractMiddleware
{
    use Singleton;

    /**
     * List of defined OAI-PMH errors.
     * @see https://openarchives.org/OAI/openarchivesprotocol.html#ErrorConditions
     */
    protected const OAI_ERRORS = [
        'badArgument' => 'The request includes illegal arguments, is missing required arguments, includes a repeated argument, or values for arguments have an illegal syntax.',
        'badResumptionToken' => 'The value of the resumptionToken argument is invalid or expired.',
        'badVerb' => 'Value of the verb argument is not a legal OAI-PMH verb, the verb argument is missing, or the verb argument is repeated.',
        'cannotDisseminateFormat' => 'The metadata format identified by the value given for the metadataPrefix argument is not supported by the item or by the repository.',
        'idDoesNotExist' => 'The value of the identifier argument is unknown or illegal in this repository.',
        'noRecordsMatch' => 'The combination of the values of the from, until, set and metadataPrefix arguments results in an empty list.',
        'noMetadataFormats' => 'There are no metadata formats available for the specified item.',
        'noSetHierarchy' => 'The repository does not support sets.'
    ];

    /**
     * The current error codes.
     *
     * @var string[] $errors
     */
    protected array $errors = [];

    /**
     * Prepare the response body.
     *
     * @return StreamInterface The response body stream
     */
    protected function getResponseBody(): StreamInterface
    {
        $document = new Document($this->requestHandler->request);
        foreach (array_unique($this->errors) as $errorCode) {
            $error = $document->createElement('error', self::OAI_ERRORS[$errorCode], true);
            $error->setAttribute('code', $errorCode);
        }
        return Utils::streamFor((string) $document);
    }

    /**
     * Check if currently there are errors to handle.
     *
     * @return bool Whether the error handler has any errors registered
     */
    public function hasErrors(): bool
    {
        return (bool) count($this->errors);
    }

    /**
     * Generate an error response if errors occured.
     *
     * @param ResponseInterface $response The incoming response
     *
     * @return ResponseInterface The error response
     */
    protected function processResponse(ResponseInterface $response): ResponseInterface
    {
        if ($this->hasErrors()) {
            $response = $response->withBody($this->getResponseBody());
        }
        return $response;
    }

    /**
     * Delegate an OAI-PMH error to the error handler.
     *
     * @param string $errorCode The error code to handle
     *
     * @return ErrorHandler The ErrorHandler instance
     *
     * @throws DomainException
     */
    public function withError(string $errorCode): ErrorHandler
    {
        if (in_array($errorCode, array_keys(self::OAI_ERRORS), true)) {
            $this->errors[] = $errorCode;
        } else {
            throw new DomainException(
                sprintf(
                    'Valid OAI-PMH error code expected, "%s" given.',
                    $errorCode
                ),
                500
            );
        }
        return $this;
    }

    /**
     * This is a singleton class, thus the constructor is private.
     *
     * Usage: Get an instance by calling ErrorHandler::getInstance()
     */
    private function __construct()
    {
    }
}
