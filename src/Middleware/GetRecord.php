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
            (string) $this->arguments['identifier'],
            (string) $this->arguments['metadataPrefix']
        );

        if (!isset($oaiRecord)) {
            if ($this->em->isValidRecordIdentifier((string) $this->arguments['identifier'])) {
                ErrorHandler::getInstance()->withError('cannotDisseminateFormat');
            } else {
                ErrorHandler::getInstance()->withError('idDoesNotExist');
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
}
