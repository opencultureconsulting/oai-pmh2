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

use GuzzleHttp\Psr7\Uri;
use OCC\OaiPmh2\Configuration;
use OCC\OaiPmh2\Middleware;
use OCC\OaiPmh2\Response;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Process the "Identify" request.
 *
 * @see https://www.openarchives.org/OAI/openarchivesprotocol.html#Identify
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 */
class Identify extends Middleware
{
    /**
     * Prepare the response body for verb "Identify".
     *
     * @param ServerRequestInterface $request The incoming request
     *
     * @return void
     */
    protected function prepareResponse(ServerRequestInterface $request): void
    {
        $response = new Response(serverRequest: $request);
        $identify = $response->createElement(
            localName: 'Identify',
            value: '',
            appendToRoot: true
        );

        $repositoryName = $response->createElement(
            localName: 'repositoryName',
            value: Configuration::getInstance()->repositoryName
        );
        $identify->appendChild(node: $repositoryName);

        $uri = Uri::composeComponents(
            scheme: $request->getUri()->getScheme(),
            authority: $request->getUri()->getAuthority(),
            path: $request->getUri()->getPath(),
            query: null,
            fragment: null
        );
        $baseURL = $response->createElement(
            localName: 'baseURL',
            value: $uri
        );
        $identify->appendChild(node: $baseURL);

        $protocolVersion = $response->createElement(
            localName: 'protocolVersion',
            value: '2.0'
        );
        $identify->appendChild(node: $protocolVersion);

        $adminEmail = $response->createElement(
            localName: 'adminEmail',
            value: Configuration::getInstance()->adminEmail
        );
        $identify->appendChild(node: $adminEmail);

        $earliestDatestamp = $response->createElement(
            localName: 'earliestDatestamp',
            value: $this->em->getEarliestDatestamp()
        );
        $identify->appendChild(node: $earliestDatestamp);

        $deletedRecord = $response->createElement(
            localName: 'deletedRecord',
            value: Configuration::getInstance()->deletedRecords
        );
        $identify->appendChild(node: $deletedRecord);

        $granularity = $response->createElement(
            localName: 'granularity',
            value: 'YYYY-MM-DDThh:mm:ssZ'
        );
        $identify->appendChild(node: $granularity);

        // TODO: Implement explicit content compression support.
        // $compressionDeflate = $response->createElement(
        //     localName: 'compression',
        //     value: 'deflate'
        // );
        // $identify->appendChild(node: $compressionDeflate);

        // $compressionGzip = $response->createElement(
        //     localName: 'compression',
        //     value: 'gzip'
        // );
        // $identify->appendChild(node: $compressionGzip);

        $this->preparedResponse = $response;
    }
}
