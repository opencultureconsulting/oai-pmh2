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

use OCC\OaiPmh2\Database;
use OCC\OaiPmh2\Document;
use OCC\OaiPmh2\Entity\Format;
use OCC\OaiPmh2\Middleware;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Process the "GetRecord" request.
 * @see https://www.openarchives.org/OAI/openarchivesprotocol.html#GetRecord
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package opencultureconsulting/oai-pmh2
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
        $params = $request->getAttributes();
        /** @var Format */
        $format = Database::getInstance()->getEntityManager()->getReference(Format::class, $params['metadataPrefix']);
        $oaiRecord = Database::getInstance()->getRecord($params['identifier'], $format);

        if (!isset($oaiRecord)) {
            if (Database::getInstance()->idDoesExist($params['identifier'])) {
                ErrorHandler::getInstance()->withError('cannotDisseminateFormat');
            } else {
                ErrorHandler::getInstance()->withError('idDoesNotExist');
            }
            return;
        }

        $document = new Document($request);
        $getRecord = $document->createElement('GetRecord', '', true);

        $record = $document->createElement('record');
        $getRecord->appendChild($record);

        $header = $document->createElement('header');
        if ($oaiRecord->getContent() === null) {
            $header->setAttribute('status', 'deleted');
        }
        $record->appendChild($header);

        $identifier = $document->createElement('identifier', $oaiRecord->getIdentifier());
        $header->appendChild($identifier);

        $datestamp = $document->createElement('datestamp', $oaiRecord->getLastChanged()->format('Y-m-d\TH:i:s\Z'));
        $header->appendChild($datestamp);

        foreach ($oaiRecord->getSets() as $set) {
            $setSpec = $document->createElement('setSpec', $set->getName());
            $header->appendChild($setSpec);
        }

        if ($oaiRecord->getContent() !== null) {
            $metadata = $document->createElement('metadata');
            $record->appendChild($metadata);

            $data = $document->importData($oaiRecord->getContent());
            $metadata->appendChild($data);
        }

        $this->preparedResponse = $document;
    }
}
