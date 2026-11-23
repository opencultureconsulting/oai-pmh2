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

namespace OCC\OaiPmh2\Tests\Middleware;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use LogicException;
use OCC\OaiPmh2\Middleware\Dispatcher;
use OCC\OaiPmh2\Middleware\ErrorHandler;
use OCC\OaiPmh2\Tests\TestCase;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(Dispatcher::class)]
#[CoversClass(ErrorHandler::class)]
final class DispatcherTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        $this->useDatabase();
        $this->addRecord('rec1');
        $this->addSet('coll');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function errorProvider(): array
    {
        return [
            'missing verb' => ['', 'badVerb'],
            'unknown verb' => ['verb=Nope', 'badVerb'],
            'repeated verb' => ['verb=Identify&verb=Identify', 'badVerb'],
            'different repeated verbs' => ['verb=Identify&verb=ListSets', 'badVerb'],
            'verb as array' => ['verb%5B%5D=Identify', 'badVerb'],
            'unknown argument' => ['verb=Identify&foo=bar', 'badArgument'],
            'repeated argument' => ['verb=GetRecord&metadataPrefix=oai_dc&identifier=rec1&identifier=rec2', 'badArgument'],
            'repeated set' => ['verb=ListIdentifiers&metadataPrefix=oai_dc&set=coll&set=other', 'badArgument'],
            'argument as array' => ['verb=ListIdentifiers&metadataPrefix=oai_dc&set%5B%5D=coll', 'badArgument']
        ];
    }

    #[Test]
    #[DataProvider('errorProvider')]
    public function itRejectsIllegalRequests(string $query, string $error): void
    {
        $xpath = $this->requestXml($query);

        $this->assertOaiError($error, $xpath);
        // Arguments of illegal requests are not echoed.
        $this->assertSame(0, $xpath->query('/oai:OAI-PMH/oai:request/@*')?->length);
        $this->assertSame('http://example.org/oai', self::text($xpath, '/oai:OAI-PMH/oai:request'));
    }

    #[Test]
    public function itDecodesArguments(): void
    {
        $xpath = $this->requestXml('verb=GetRecord&metadataPrefix=oai_dc&identifier=a%26b+c%3Dd');

        $this->assertOaiError('idDoesNotExist', $xpath);
        $this->assertSame('a&b c=d', self::text($xpath, '/oai:OAI-PMH/oai:request/@identifier'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function formContentTypeProvider(): array
    {
        return [
            'plain' => ['application/x-www-form-urlencoded'],
            'with charset' => ['application/x-www-form-urlencoded; charset=UTF-8'],
            'other case and whitespace' => ['Application/X-WWW-Form-URLEncoded ; charset=utf-8']
        ];
    }

    #[Test]
    #[DataProvider('formContentTypeProvider')]
    public function itAcceptsPostRequests(string $contentType): void
    {
        $xpath = $this->requestXml(
            'verb=GetRecord&metadataPrefix=oai_dc&identifier=rec1',
            'POST',
            ['Content-Type' => $contentType]
        );

        $this->assertSame([], self::errors($xpath));
        $this->assertSame('rec1', self::text($xpath, '//oai:GetRecord//oai:header/oai:identifier'));
    }

    #[Test]
    public function itRejectsRepeatedArgumentsInPostRequests(): void
    {
        $xpath = $this->requestXml(
            'verb=ListSets&verb=ListSets',
            'POST',
            ['Content-Type' => 'application/x-www-form-urlencoded']
        );

        $this->assertOaiError('badVerb', $xpath);
    }

    #[Test]
    public function itIgnoresPostRequestsWithOtherContentTypes(): void
    {
        $xpath = $this->requestXml('verb=Identify', 'POST', ['Content-Type' => 'text/plain']);

        $this->assertOaiError('badVerb', $xpath);
    }

    #[Test]
    public function itIgnoresOtherHttpMethods(): void
    {
        $xpath = $this->requestXml('verb=Identify', 'PUT', ['Content-Type' => 'application/x-www-form-urlencoded']);

        $this->assertOaiError('badVerb', $xpath);
    }

    #[Test]
    public function itReportsAllErrors(): void
    {
        $xpath = $this->requestXml('verb=ListIdentifiers&metadataPrefix=marc&from=yesterday');

        $this->assertSame(['cannotDisseminateFormat', 'badArgument'], self::errors($xpath));
    }

    #[Test]
    public function itRequiresAQueueRequestHandler(): void
    {
        $handler = new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response();
            }
        };

        $this->expectException(LogicException::class);

        (new Dispatcher())->process(new ServerRequest('GET', 'http://example.org/oai?verb=Identify'), $handler);
    }
}
