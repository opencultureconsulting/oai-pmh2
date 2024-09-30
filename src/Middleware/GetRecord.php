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
 */
class GetRecord extends Middleware
{
    /**
     * Prepare the response body for verb "GetRecord".
     *
     * @param ServerRequestInterface $request The incoming request
     *
     * @return void
     */
    protected function prepareResponse(ServerRequestInterface $request): void
    {
        $oaiRecord = $this->em->getRecord(
            identifier: (string) $this->arguments['identifier'],
            format: (string) $this->arguments['metadataPrefix']
        );

        if (!isset($oaiRecord)) {
            if ($this->em->isValidRecordIdentifier(identifier: (string) $this->arguments['identifier'])) {
                ErrorHandler::getInstance()->withError(errorCode: 'cannotDisseminateFormat');
            } else {
                ErrorHandler::getInstance()->withError(errorCode: 'idDoesNotExist');
            }
            return;
        }

        $response = new Response(serverRequest: $request);
        $getRecord = $response->createElement(
            localName: 'GetRecord',
            value: '',
            appendToRoot: true
        );

        $record = $response->createElement(localName: 'record');
        $getRecord->appendChild(node: $record);

        $header = $response->createElement(localName: 'header');
        if (!$oaiRecord->hasContent()) {
            $header->setAttribute(
                qualifiedName: 'status',
                value: 'deleted'
            );
        }
        $record->appendChild(node: $header);

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

        foreach ($oaiRecord->getSets() as $set) {
            $setSpec = $response->createElement(
                localName: 'setSpec',
                value: $set->getName()
            );
            $header->appendChild(node: $setSpec);
        }

        if ($oaiRecord->hasContent()) {
            $metadata = $response->createElement(localName: 'metadata');
            $record->appendChild(node: $metadata);

            $data = $response->importData(data: $oaiRecord->getContent());
            $metadata->appendChild(node: $data);
        }

        $this->preparedResponse = $response;
    }
}
