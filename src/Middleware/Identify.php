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
use OCC\OaiPmh2\Database;
use OCC\OaiPmh2\Document;
use OCC\OaiPmh2\Middleware;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Process the "Identify" request.
 * @see https://www.openarchives.org/OAI/openarchivesprotocol.html#Identify
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package opencultureconsulting/oai-pmh2
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
        $document = new Document($request);
        $identify = $document->createElement('Identify', '', true);

        $name = Configuration::getInstance()->repositoryName;
        $repositoryName = $document->createElement('repositoryName', $name);
        $identify->appendChild($repositoryName);

        $uri = Uri::composeComponents(
            $request->getUri()->getScheme(),
            $request->getUri()->getAuthority(),
            $request->getUri()->getPath(),
            null,
            null
        );
        $baseURL = $document->createElement('baseURL', $uri);
        $identify->appendChild($baseURL);

        $protocolVersion = $document->createElement('protocolVersion', '2.0');
        $identify->appendChild($protocolVersion);

        $email = Configuration::getInstance()->adminEmail;
        $adminEmail = $document->createElement('adminEmail', $email);
        $identify->appendChild($adminEmail);

        $datestamp = Database::getInstance()->getEarliestDatestamp();
        $earliestDatestamp = $document->createElement('earliestDatestamp', $datestamp);
        $identify->appendChild($earliestDatestamp);

        $deletedRecord = $document->createElement('deletedRecord', 'transient');
        $identify->appendChild($deletedRecord);

        $granularity = $document->createElement('granularity', 'YYYY-MM-DDThh:mm:ssZ');
        $identify->appendChild($granularity);

        // TODO: Implement explicit content compression support.
        // $compressionDeflate = $document->createElement('compression', 'deflate');
        // $identify->appendChild($compressionDeflate);

        // $compressionGzip = $document->createElement('compression', 'gzip');
        // $identify->appendChild($compressionGzip);

        $this->preparedResponse = $document;
    }
}
