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
    protected function prepareResponse(ServerRequestInterface $request): void
    {
        $this->checkResumptionToken();

        $records = $this->em->getRecords(
            verb: $this->arguments['verb'],
            metadataPrefix: (string) $this->arguments['metadataPrefix'],
            counter: $this->arguments['counter'],
            from: $this->arguments['from'],
            until: $this->arguments['until'],
            set: $this->arguments['set']
        );
        if (count($records) === 0) {
            ErrorHandler::getInstance()->withError(errorCode: 'noRecordsMatch');
            return;
        }

        $response = new Response(serverRequest: $request);
        $list = $response->createElement(
            localName: $this->arguments['verb'],
            value: '',
            appendToRoot: true
        );
        $baseNode = $list;

        foreach ($records as $oaiRecord) {
            if ($this->arguments['verb'] === 'ListRecords') {
                $record = $response->createElement(localName: 'record');
                $list->appendChild(node: $record);
                $baseNode = $record;
            }

            $header = $response->createElement(localName: 'header');
            $baseNode->appendChild(node: $header);

            $identifier = $response->createElement(
                localName: 'identifier',
                value: $oaiRecord->getIdentifier()
            );
            $header->appendChild(node: $identifier);

            $datestamp = $response->createElement(
                localName: 'datestamp',
                value: $oaiRecord->getLastChanged()->format(format: 'Y-m-d\TH:i:s\Z')
            );
            $header->appendChild(node: $datestamp);

            foreach ($oaiRecord->getSets() as $oaiSet) {
                $setSpec = $response->createElement(
                    localName: 'setSpec',
                    value: $oaiSet->getName()
                );
                $header->appendChild(node: $setSpec);
            }

            if (!$oaiRecord->hasContent()) {
                $header->setAttribute(
                    qualifiedName: 'status',
                    value: 'deleted'
                );
            } elseif ($this->arguments['verb'] === 'ListRecords') {
                $metadata = $response->createElement(localName: 'metadata');
                $baseNode->appendChild(node: $metadata);

                $data = $response->importData(data: $oaiRecord->getContent());
                $metadata->appendChild(node: $data);
            }
        }

        $this->preparedResponse = $response;

        $this->addResumptionToken(
            node: $list,
            token: $records->getResumptionToken() ?? null
        );
    }
}
