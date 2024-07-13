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

use OCC\OaiPmh2\Database;
use OCC\OaiPmh2\Document;
use OCC\OaiPmh2\Entity\Format;
use OCC\OaiPmh2\Middleware;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Process the "ListMetadataFormats" request.
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
        /** @var ?string */
        $identifier = $request->getAttribute('identifier');
        $formats = Database::getInstance()->getMetadataFormats($identifier);

        if (count($formats) === 0) {
            if (!isset($identifier) || Database::getInstance()->idDoesExist($identifier)) {
                ErrorHandler::getInstance()->withError('noMetadataFormats');
            } else {
                ErrorHandler::getInstance()->withError('idDoesNotExist');
            }
            return;
        }

        $document = new Document($request);
        $listMetadataFormats = $document->createElement('ListMetadataFormats', '', true);

        /** @var Format $oaiFormat */
        foreach ($formats as $oaiFormat) {
            $metadataFormat = $document->createElement('metadataFormat');
            $listMetadataFormats->appendChild($metadataFormat);

            $metadataPrefix = $document->createElement('metadataPrefix', $oaiFormat->getPrefix());
            $metadataFormat->appendChild($metadataPrefix);

            $schema = $document->createElement('schema', $oaiFormat->getSchema());
            $metadataFormat->appendChild($schema);

            $metadataNamespace = $document->createElement('metadataNamespace', $oaiFormat->getNamespace());
            $metadataFormat->appendChild($metadataNamespace);
        }

        $this->preparedResponse = $document;
    }
}
