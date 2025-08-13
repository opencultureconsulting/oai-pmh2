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
use OCC\OaiPmh2\Response;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Process the "GetRecord" request.
 *
 * @see https://www.openarchives.org/OAI/openarchivesprotocol.html#GetRecord
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 *
 * @template RequestParameters of array{
 *     verb: 'GetRecord',
 *     identifier: non-empty-string,
 *     metadataPrefix: non-empty-string
 * }
 * @extends Middleware<RequestParameters>
 */
final class GetRecord extends Middleware
{
    /**
     * Prepare the response body for verb "GetRecord".
     *
     * @param ServerRequestInterface $request The incoming request
     *
     * @return void
     */
    #[\Override]
    protected function prepareResponse(ServerRequestInterface $request): void
    {
        $oaiRecord = $this->em->getRecord(
            $this->arguments['identifier'],
            $this->arguments['metadataPrefix']
        );

        if (!isset($oaiRecord)) {
            if ($this->em->isValidRecordIdentifier($this->arguments['identifier'])) {
                $this->errorHandler->withError('cannotDisseminateFormat');
            } else {
                $this->errorHandler->withError('idDoesNotExist');
            }
            return;
        }

        $response = new Response($request);
        $getRecord = $response->createElement('GetRecord', '', true);

        $record = $response->createElement('record');
        $getRecord->appendChild($record);

        $header = $response->createElement('header');
        if (!$oaiRecord->hasContent()) {
            $header->setAttribute('status', 'deleted');
        }
        $record->appendChild($header);

        $identifier = $response->createElement('identifier', $oaiRecord->getIdentifier());
        $header->appendChild($identifier);

        $datestamp = $response->createElement(
            'datestamp',
            $oaiRecord->getLastChanged()->format('Y-m-d\TH:i:s\Z')
        );
        $header->appendChild($datestamp);

        foreach ($oaiRecord->getSets() as $set) {
            $setSpec = $response->createElement('setSpec', $set->getName());
            $header->appendChild($setSpec);
        }

        if ($oaiRecord->hasContent()) {
            $metadata = $response->createElement('metadata');
            $record->appendChild($metadata);

            $data = $response->importData($oaiRecord->getContent());
            $metadata->appendChild($data);
        }

        $this->preparedResponse = $response;
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
    #[\Override]
    protected function validateArguments(): bool
    {
        if (
            count($this->arguments) !== 3
            or !array_key_exists('identifier', $this->arguments)
            or !array_key_exists('metadataPrefix', $this->arguments)
        ) {
            $this->errorHandler->withError('badArgument');
        } else {
            $this->validateMetadataPrefix();
        }
        return !$this->errorHandler->hasErrors();
    }
}
