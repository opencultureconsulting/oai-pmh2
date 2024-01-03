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

use OCC\OaiPmh2\Configuration;
use OCC\OaiPmh2\Database;
use OCC\OaiPmh2\Database\Record;
use OCC\OaiPmh2\Document;
use OCC\OaiPmh2\Middleware;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Process the "ListIdentifiers" request.
 * @see https://www.openarchives.org/OAI/openarchivesprotocol.html#ListIdentifiers
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package opencultureconsulting/oai-pmh2
 */
class ListIdentifiers extends Middleware
{
    /**
     * Prepare the response body for verb "ListIdentifiers" and "ListRecords".
     *
     * @param ServerRequestInterface $request The incoming request
     *
     * @return void
     */
    protected function prepareResponse(ServerRequestInterface $request): void
    {
        $counter = 0;
        $completeListSize = 0;
        $maxRecords = Configuration::getInstance()->maxRecords;

        $params = $request->getAttributes();
        $verb = $params['verb'];
        $metadataPrefix = $params['metadataPrefix'] ?? '';
        $from = $params['from'] ?? null;
        $until = $params['until'] ?? null;
        $set = $params['set'] ?? null;
        $resumptionToken = $params['resumptionToken'] ?? null;

        if (isset($resumptionToken)) {
            $oldToken = Database::getInstance()->getResumptionToken($resumptionToken, $verb);
            if (!isset($oldToken)) {
                ErrorHandler::getInstance()->withError('badResumptionToken');
                return;
            } else {
                foreach ($oldToken->getParameters() as $key => $value) {
                    $$key = $value;
                }
            }
        }
        $prefixes = Database::getInstance()->getMetadataFormats();
        if (!in_array($metadataPrefix, array_keys($prefixes->getQueryResult()), true)) {
            ErrorHandler::getInstance()->withError('cannotDisseminateFormat');
            return;
        }
        if (isset($set)) {
            $sets = Database::getInstance()->getSets();
            if (!in_array($set, array_keys($sets->getQueryResult()), true)) {
                ErrorHandler::getInstance()->withError('noSetHierarchy');
                return;
            }
        }

        $records = Database::getInstance()->getRecords($verb, $metadataPrefix, $counter, $from, $until, $set);
        if (count($records) === 0) {
            ErrorHandler::getInstance()->withError('noRecordsMatch');
            return;
        } elseif ($records->getResumptionToken() !== null) {
            $newToken = $records->getResumptionToken();
            $completeListSize = $newToken->getParameters()['completeListSize'];
        }

        $document = new Document($request);
        $list = $document->createElement($verb, '', true);

        /** @var Record $oaiRecord */
        foreach ($records as $oaiRecord) {
            if ($verb === 'ListIdentifiers') {
                $baseNode = $list;
            } else {
                $record = $document->createElement('record');
                $list->appendChild($record);
                $baseNode = $record;
            }

            $header = $document->createElement('header');
            if ($oaiRecord->getContent() === '') {
                $header->setAttribute('status', 'deleted');
            }
            $baseNode->appendChild($header);

            $identifier = $document->createElement('identifier', $oaiRecord->getIdentifier());
            $header->appendChild($identifier);

            $datestamp = $document->createElement('datestamp', $oaiRecord->getLastChanged()->format('Y-m-d\TH:i:s\Z'));
            $header->appendChild($datestamp);

            foreach ($oaiRecord->getSets() as $oaiSet) {
                $setSpec = $document->createElement('setSpec', $oaiSet->getName());
                $header->appendChild($setSpec);
            }

            if ($verb === 'ListRecords' && $oaiRecord->getContent() !== '') {
                $metadata = $document->createElement('metadata');
                $baseNode->appendChild($metadata);

                $data = $document->importData($oaiRecord->getContent());
                $metadata->appendChild($data);
            }
        }

        if (isset($oldToken) || isset($newToken)) {
            $resumptionToken = $document->createElement('resumptionToken');
            $list->appendChild($resumptionToken);
            if (isset($newToken)) {
                $resumptionToken->nodeValue = $newToken->getToken();
                $resumptionToken->setAttribute(
                    'expirationDate',
                    $newToken->getValidUntil()->format('Y-m-d\TH:i:s\Z')
                );
            }
            $resumptionToken->setAttribute(
                'completeListSize',
                (string) $completeListSize
            );
            $resumptionToken->setAttribute(
                'cursor',
                (string) ($counter * $maxRecords)
            );
        }

        $this->preparedResponse = $document;
    }
}
