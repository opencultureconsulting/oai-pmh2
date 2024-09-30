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
 * Process the "ListSets" request.
 *
 * @see https://openarchives.org/OAI/openarchivesprotocol.html#ListSets
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
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
        $this->checkResumptionToken();

        $sets = $this->em->getSets(counter: $this->arguments['counter']);

        if (count($sets) === 0) {
            ErrorHandler::getInstance()->withError(errorCode: 'noSetHierarchy');
            return;
        }

        $response = new Response(serverRequest: $request);
        $list = $response->createElement(
            localName: 'ListSets',
            value: '',
            appendToRoot: true
        );

        foreach ($sets as $oaiSet) {
            $set = $response->createElement(localName: 'set');
            $list->appendChild(node: $set);

            $setSpec = $response->createElement(
                localName: 'setSpec',
                value: $oaiSet->getSpec()
            );
            $set->appendChild(node: $setSpec);

            $setName = $response->createElement(
                localName: 'setName',
                value: $oaiSet->getName()
            );
            $set->appendChild(node: $setName);

            if ($oaiSet->hasDescription()) {
                $setDescription = $response->createElement(localName: 'setDescription');
                $set->appendChild(node: $setDescription);

                $data = $response->importData(data: $oaiSet->getDescription());
                $setDescription->appendChild(node: $data);
            }
        }

        $this->preparedResponse = $response;

        $this->addResumptionToken(
            node: $list,
            token: $sets->getResumptionToken() ?? null
        );
    }
}
