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
final class ListMetadataFormats extends Middleware
{
    /**
     * Prepare the response body for verb "ListMetadataFormats".
     *
     * @param ServerRequestInterface $request The incoming request
     *
     * @return void
     */
    #[\Override]
    protected function prepareResponse(ServerRequestInterface $request): void
    {
        $formats = $this->em->getMetadataFormats($this->arguments['identifier']);

        if (count($formats) === 0) {
            if (
                !isset($this->arguments['identifier'])
                || $this->em->isValidRecordIdentifier($this->arguments['identifier'])
            ) {
                ErrorHandler::getInstance()->withError('noMetadataFormats');
            } else {
                ErrorHandler::getInstance()->withError('idDoesNotExist');
            }
            return;
        }

        $response = new Response($request);
        $listMetadataFormats = $response->createElement('ListMetadataFormats', '', true);

        foreach ($formats as $oaiFormat) {
            $metadataFormat = $response->createElement('metadataFormat');
            $listMetadataFormats->appendChild($metadataFormat);

            $metadataPrefix = $response->createElement(
                'metadataPrefix',
                $oaiFormat->getPrefix()
            );
            $metadataFormat->appendChild($metadataPrefix);

            $schema = $response->createElement(
                'schema',
                $oaiFormat->getSchema()
            );
            $metadataFormat->appendChild($schema);

            $metadataNamespace = $response->createElement(
                'metadataNamespace',
                $oaiFormat->getNamespace()
            );
            $metadataFormat->appendChild($metadataNamespace);
        }

        $this->preparedResponse = $response;
    }
}
