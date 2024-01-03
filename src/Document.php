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

namespace OCC\OaiPmh2;

use DOMDocument;
use DOMElement;
use DOMException;
use DOMNode;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\ServerRequestInterface;

/**
 * An OAI-PMH XML response object.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package opencultureconsulting/oai-pmh2
 */
class Document
{
    /**
     * This holds the DOMDocument of the OAI-PMH XML response.
     */
    protected DOMDocument $dom;

    /**
     * This holds the root node of the OAI-PMH XML response.
     */
    protected DOMElement $rootNode;

    /**
     * Create a new XML element.
     *
     * @param string $localName The local name for the element
     * @param string $value The optional value for the element
     * @param bool $appendToRoot Append the new element to the root node?
     *
     * @return DOMElement The newly created element
     */
    public function createElement(string $localName, string $value = '', bool $appendToRoot = false): DOMElement
    {
        $node = $this->dom->createElement(
            $localName,
            htmlspecialchars($value, ENT_XML1, 'UTF-8')
        );
        if ($appendToRoot) {
            $this->rootNode->appendChild($node);
        }
        return $node;
    }

    /**
     * Import XML data into response document.
     *
     * @param string $data The XML data
     *
     * @return DOMNode The imported XML node
     *
     * @throws DOMException
     */
    public function importData(string $data): DOMNode
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = false;
        if ($document->loadXML($data) === true) {
            /** @var DOMElement */
            $rootNode = $document->documentElement;
            $node = $this->dom->importNode($rootNode, true);
            return $node;
        } else {
            throw new DOMException(
                'Could not import the XML data. Most likely it is not well-formed.',
                500
            );
        }
    }

    /**
     * Create an OAI-PMH XML response.
     *
     * @param ServerRequestInterface $serverRequest The PSR-7 HTTP Server Request
     */
    public function __construct(ServerRequestInterface $serverRequest)
    {
        $uri = $serverRequest->getUri();

        // Create XML document.
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->preserveWhiteSpace = false;

        // Add processing instructions.
        $basePath = $uri->getPath();
        if (str_ends_with($basePath, 'index.php')) {
            $basePath = pathinfo($basePath, PATHINFO_DIRNAME);
        }
        $stylesheet = Uri::composeComponents(
            $uri->getScheme(),
            $uri->getAuthority(),
            rtrim($basePath, '/') . '/resources/stylesheet.xsl',
            null,
            null
        );
        $xslt = $this->dom->createProcessingInstruction(
            'xml-stylesheet',
            sprintf(
                'type="text/xsl" href="%s"',
                $stylesheet
            )
        );
        $this->dom->appendChild($xslt);

        // Add root element "OAI-PMH".
        $root = $this->dom->createElement('OAI-PMH');
        $this->dom->appendChild($root);
        $root->setAttribute(
            'xmlns',
            'http://www.openarchives.org/OAI/2.0/'
        );
        $root->setAttribute(
            'xmlns:xsi',
            'http://www.w3.org/2001/XMLSchema-instance'
        );
        $root->setAttribute(
            'xsi:schemaLocation',
            'http://www.openarchives.org/OAI/2.0/ https://www.openarchives.org/OAI/2.0/OAI-PMH.xsd'
        );

        // Add element "responseDate".
        $responseDate = $this->dom->createElement('responseDate', gmdate('Y-m-d\TH:i:s\Z'));
        $root->appendChild($responseDate);

        // Add element "request".
        $baseUrl = Uri::composeComponents(
            $uri->getScheme(),
            $uri->getAuthority(),
            $uri->getPath(),
            null,
            null
        );
        $request = $this->dom->createElement('request', $baseUrl);
        $root->appendChild($request);
        foreach ($serverRequest->getAttributes() as $param => $value) {
            $request->setAttribute(
                $param,
                htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8')
            );
        }

        $this->rootNode = $root;
    }

    /**
     * Serialize the OAI-PMH XML response.
     *
     * @return string The XML output
     */
    public function __toString(): string
    {
        $this->dom->formatOutput = true;
        return (string) $this->dom->saveXML();
    }
}
