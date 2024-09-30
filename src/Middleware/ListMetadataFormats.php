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
 * Process the "ListMetadataFormats" request.
 *
 * @see https://www.openarchives.org/OAI/openarchivesprotocol.html#ListMetadataFormats
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 */
class ListMetadataFormats extends Middleware
{
    /**
     * Prepare the response body for verb "ListMetadataFormats".
     *
     * @param ServerRequestInterface $request The incoming request
     *
     * @return void
     */
    protected function prepareResponse(ServerRequestInterface $request): void
    {
        $formats = $this->em->getMetadataFormats(recordIdentifier: $this->arguments['identifier']);

        if (count($formats) === 0) {
            if (
                !isset($this->arguments['identifier'])
                || $this->em->isValidRecordIdentifier(identifier: $this->arguments['identifier'])
            ) {
                ErrorHandler::getInstance()->withError(errorCode: 'noMetadataFormats');
            } else {
                ErrorHandler::getInstance()->withError(errorCode: 'idDoesNotExist');
            }
            return;
        }

        $response = new Response(serverRequest: $request);
        $listMetadataFormats = $response->createElement(
            localName: 'ListMetadataFormats',
            value: '',
            appendToRoot: true
        );

        foreach ($formats as $oaiFormat) {
            $metadataFormat = $response->createElement(localName: 'metadataFormat');
            $listMetadataFormats->appendChild(node: $metadataFormat);

            $metadataPrefix = $response->createElement(
                localName: 'metadataPrefix',
                value: $oaiFormat->getPrefix()
            );
            $metadataFormat->appendChild(node: $metadataPrefix);

            $schema = $response->createElement(
                localName: 'schema',
                value: $oaiFormat->getSchema()
            );
            $metadataFormat->appendChild(node: $schema);

            $metadataNamespace = $response->createElement(
                localName: 'metadataNamespace',
                value: $oaiFormat->getNamespace()
            );
            $metadataFormat->appendChild(node: $metadataNamespace);
        }

        $this->preparedResponse = $response;
    }
}
