<?php

/**
 * OAI-PMH 2.0 Data Provider
 * Copyright (C) 2026 Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
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

namespace OCC\OaiPmh2\Tests;

use DOMDocument;
use DOMXPath;
use GuzzleHttp\Psr7\ServerRequest;
use OCC\OaiPmh2\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Response::class)]
final class ResponseTest extends TestCase
{
    /**
     * Parse a response document.
     *
     * @param Response $response The response
     *
     * @return DOMXPath The response document
     */
    private static function parse(Response $response): DOMXPath
    {
        $document = new DOMDocument();
        $document->loadXML((string) $response);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('oai', self::OAI_NAMESPACE);
        return $xpath;
    }

    #[Test]
    public function itCreatesTheResponseSkeleton(): void
    {
        $before = gmdate('Y-m-d\TH:i:s\Z');
        $xpath = self::parse(new Response(new ServerRequest('GET', 'http://example.org/oai?verb=Identify')));
        $after = gmdate('Y-m-d\TH:i:s\Z');

        $responseDate = (string) self::text($xpath, '/oai:OAI-PMH/oai:responseDate');
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', $responseDate);
        $this->assertGreaterThanOrEqual($before, $responseDate);
        $this->assertLessThanOrEqual($after, $responseDate);
        $this->assertSame('http://example.org/oai', self::text($xpath, '/oai:OAI-PMH/oai:request'));
    }

    #[Test]
    public function itEscapesRequestAttributesOnlyOnce(): void
    {
        $request = (new ServerRequest('GET', 'http://example.org/oai'))
            ->withAttribute('verb', 'GetRecord')
            ->withAttribute('identifier', 'a&b"c<d>\'e');
        $response = new Response($request);

        $this->assertStringContainsString('identifier="a&amp;b&quot;c&lt;d&gt;\'e"', (string) $response);
        $this->assertSame('a&b"c<d>\'e', self::text(self::parse($response), '/oai:OAI-PMH/oai:request/@identifier'));
    }

    #[Test]
    public function itEscapesElementValues(): void
    {
        $response = new Response(new ServerRequest('GET', 'http://example.org/oai'));
        $response->createElement('repositoryName', 'A & B <C>', true);

        $this->assertSame('A & B <C>', self::text(self::parse($response), '/oai:OAI-PMH/oai:repositoryName'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function stylesheetProvider(): array
    {
        return [
            'directory' => ['http://example.org/oai/', 'http://example.org/oai/resources/stylesheet.xsl'],
            'index.php' => ['http://example.org/oai/index.php', 'http://example.org/oai/resources/stylesheet.xsl'],
            'root index.php' => ['http://example.org/index.php', 'http://example.org/resources/stylesheet.xsl'],
            'custom port' => ['https://example.org:8443/index.php', 'https://example.org:8443/resources/stylesheet.xsl']
        ];
    }

    #[Test]
    #[DataProvider('stylesheetProvider')]
    public function itLinksTheStylesheet(string $requestUri, string $stylesheet): void
    {
        $response = (string) new Response(new ServerRequest('GET', $requestUri . '?verb=Identify'));

        $this->assertStringContainsString(
            sprintf('<?xml-stylesheet type="text/xsl" href="%s"?>', $stylesheet),
            $response
        );
    }

    #[Test]
    public function itImportsXmlData(): void
    {
        $response = new Response(new ServerRequest('GET', 'http://example.org/oai'));
        $node = $response->importData(self::CONTENT);

        $this->assertSame('dc', $node->localName);
        $this->assertSame('Test', $node->textContent);
    }
}
