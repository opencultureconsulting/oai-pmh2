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

        $sets = $this->em->getSets($this->arguments['counter']);

        if (count($sets) === 0) {
            ErrorHandler::getInstance()->withError('noSetHierarchy');
            return;
        }

        $response = new Response($request);
        $list = $response->createElement('ListSets', '', true);

        foreach ($sets as $oaiSet) {
            $set = $response->createElement('set');
            $list->appendChild($set);

            $setSpec = $response->createElement(
                'setSpec',
                $oaiSet->getSpec()
            );
            $set->appendChild($setSpec);

            $setName = $response->createElement(
                'setName',
                $oaiSet->getName()
            );
            $set->appendChild($setName);

            if ($oaiSet->hasDescription()) {
                $setDescription = $response->createElement('setDescription');
                $set->appendChild($setDescription);

                $data = $response->importData($oaiSet->getDescription());
                $setDescription->appendChild($data);
            }
        }

        $this->preparedResponse = $response;

        $this->addResumptionToken($list, $sets->getResumptionToken() ?? null);
    }
}
