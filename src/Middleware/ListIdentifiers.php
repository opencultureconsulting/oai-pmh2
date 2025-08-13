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
 * Process the "ListIdentifiers" request.
 *
 * @see https://www.openarchives.org/OAI/openarchivesprotocol.html#ListIdentifiers
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 *
 * @template RequestParameters of array{
 *     verb: 'ListIdentifiers'|'ListRecords',
 *     metadataPrefix: non-empty-string,
 *     from?: non-empty-string,
 *     until?: non-empty-string,
 *     set?: non-empty-string,
 *     resumptionToken?: non-empty-string
 * }
 * @extends Middleware<RequestParameters>
 */
class ListIdentifiers extends Middleware
{
    /**
     * Prepare the response body for verbs "ListIdentifiers" and "ListRecords".
     *
     * @param ServerRequestInterface $request The incoming request
     *
     * @return void
     */
    #[\Override]
    protected function prepareResponse(ServerRequestInterface $request): void
    {
        $records = $this->em->getRecords(
            $this->arguments['verb'],
            $this->arguments['metadataPrefix'],
            $this->flowControl['counter'],
            $this->arguments['from'] ?? null,
            $this->arguments['until'] ?? null,
            $this->arguments['set'] ?? null
        );
        if (count($records) === 0) {
            $this->errorHandler->withError('noRecordsMatch');
            return;
        }

        $response = new Response($request);
        $list = $response->createElement($this->arguments['verb'], '', true);
        $baseNode = $list;

        foreach ($records as $oaiRecord) {
            if ($this->arguments['verb'] === 'ListRecords') {
                $record = $response->createElement('record');
                $list->appendChild($record);
                $baseNode = $record;
            }

            $header = $response->createElement('header');
            $baseNode->appendChild($header);

            $identifier = $response->createElement(
                'identifier',
                $oaiRecord->getIdentifier()
            );
            $header->appendChild($identifier);

            $datestamp = $response->createElement(
                'datestamp',
                $oaiRecord->getLastChanged()->format('Y-m-d\TH:i:s\Z')
            );
            $header->appendChild($datestamp);

            foreach ($oaiRecord->getSets() as $oaiSet) {
                $setSpec = $response->createElement('setSpec', $oaiSet->getName());
                $header->appendChild($setSpec);
            }

            if (!$oaiRecord->hasContent()) {
                $header->setAttribute('status', 'deleted');
            } elseif ($this->arguments['verb'] === 'ListRecords') {
                $metadata = $response->createElement('metadata');
                $baseNode->appendChild($metadata);

                $data = $response->importData($oaiRecord->getContent());
                $metadata->appendChild($data);
            }
        }

        $this->preparedResponse = $response;

        $this->addResumptionToken($list, $records->getResumptionToken() ?? null);
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
        $this->validateResumptionToken();
        if (
            !array_key_exists('metadataPrefix', $this->arguments)
            or array_key_exists('identifier', $this->arguments)
        ) {
            $this->errorHandler->withError('badArgument');
        } else {
            $this->validateMetadataPrefix();
        }
        if (
            array_key_exists('from', $this->arguments)
            or array_key_exists('until', $this->arguments)
        ) {
            $this->validateDateTime();
        }
        if (array_key_exists('set', $this->arguments)) {
            $this->validateSet();
        }
        return !$this->errorHandler->hasErrors();
    }
}
