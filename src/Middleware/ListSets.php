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
use OCC\OaiPmh2\Document;
use OCC\OaiPmh2\Entity\Set;
use OCC\OaiPmh2\Middleware;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Process the "ListSets" request.
 * @see https://openarchives.org/OAI/openarchivesprotocol.html#ListSets
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package opencultureconsulting/oai-pmh2
 *
 * @template Sets of array<string, Set>
 */
class ListSets extends Middleware
{
    /**
     * Prepare the response body for verb "ListSets".
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

        /** @var ?string */
        $token = $request->getAttribute('resumptionToken');
        if (isset($token)) {
            $oldToken = Database::getInstance()->getResumptionToken($token, 'ListSets');
            if (!isset($oldToken)) {
                ErrorHandler::getInstance()->withError('badResumptionToken');
                return;
            } else {
                foreach ($oldToken->getParameters() as $key => $value) {
                    $$key = $value;
                }
            }
        }

        $sets = Database::getInstance()->getSets($counter);
        if (count($sets) === 0) {
            ErrorHandler::getInstance()->withError('noSetHierarchy');
            return;
        } elseif ($sets->getResumptionToken() !== null) {
            $newToken = $sets->getResumptionToken();
            $completeListSize = $newToken->getParameters()['completeListSize'];
        }

        $document = new Document($request);
        $list = $document->createElement('ListSets', '', true);

        /** @var Set $oaiSet */
        foreach ($sets as $oaiSet) {
            $set = $document->createElement('set');
            $list->appendChild($set);

            $setSpec = $document->createElement('setSpec', $oaiSet->getSpec());
            $set->appendChild($setSpec);

            $setName = $document->createElement('setName', $oaiSet->getName());
            $set->appendChild($setName);

            if ($oaiSet->hasDescription()) {
                $setDescription = $document->createElement('setDescription');
                $set->appendChild($setDescription);

                /** @var string */
                $description = $oaiSet->getDescription();
                $data = $document->importData($description);
                $setDescription->appendChild($data);
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
