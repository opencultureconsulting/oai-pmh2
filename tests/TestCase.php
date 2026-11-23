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

use DateTime;
use DOMDocument;
use DOMXPath;
use Doctrine\ORM\Tools\SchemaTool;
use GuzzleHttp\Psr7\ServerRequest;
use OCC\OaiPmh2\Configuration;
use OCC\OaiPmh2\EntityManager;
use OCC\OaiPmh2\Entity\Format;
use OCC\OaiPmh2\Entity\Record;
use OCC\OaiPmh2\Entity\Set;
use OCC\OaiPmh2\Middleware\Dispatcher;
use OCC\OaiPmh2\Middleware\ErrorHandler;
use OCC\OaiPmh2\Validator\ConfigurationValidator;
use OCC\PSR15\MiddlewareQueue;
use OCC\PSR15\QueueRequestHandler;
use Override;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Base class for all tests.
 *
 * Tests never touch the configuration, database or caches of the local installation: the configuration is injected,
 * every test gets its own in-memory SQLite database and all caches are kept in memory.
 */
abstract class TestCase extends PHPUnitTestCase
{
    /**
     * The XML namespace of OAI-PMH responses.
     */
    protected const OAI_NAMESPACE = 'http://www.openarchives.org/OAI/2.0/';

    /**
     * The default settings for tests.
     *
     * @var array<string, mixed>
     */
    protected const SETTINGS = [
        'repositoryName' => 'Test Repository',
        'adminEmail' => 'admin@example.org',
        'database' => 'sqlite3:///:memory:',
        'metadataPrefix' => [
            'oai_dc' => [
                'namespace' => 'http://www.openarchives.org/OAI/2.0/oai_dc/',
                'schema' => 'https://www.openarchives.org/OAI/2.0/oai_dc.xsd'
            ],
            'mods' => [
                'namespace' => 'http://www.loc.gov/mods/v3',
                'schema' => 'https://www.loc.gov/standards/mods/v3/mods-3-8.xsd'
            ]
        ],
        'deletedRecords' => 'transient',
        'maxRecords' => 2,
        'tokenValid' => 1800,
        'maintenanceMode' => false
    ];

    /**
     * A valid XML document to use as record content.
     */
    protected const CONTENT = '<dc xmlns="http://purl.org/dc/elements/1.1/"><title>Test</title></dc>';

    /**
     * Whether the Doctrine proxy classes were generated already (only needed for PHP < 8.4).
     */
    private static bool $proxiesGenerated = false;

    /**
     * The entity manager connected to the test database (if any).
     */
    protected EntityManager $em;

    /**
     * Temporary files to remove after the test.
     *
     * @var list<string>
     */
    private array $tempFiles = [];

    /**
     * Use the test settings, optionally overriding some of them.
     *
     * @param array<string, mixed> $overrides Settings to override
     *
     * @return void
     */
    protected function useConfiguration(array $overrides = []): void
    {
        $settings = array_replace(static::SETTINGS, $overrides);
        ConfigurationValidator::validate($settings);
        $configuration = (new ReflectionClass(Configuration::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(Configuration::class, 'settings'))->setValue($configuration, $settings);
        self::setSingleton(Configuration::class, $configuration);
    }

    /**
     * Use a new in-memory database with all configured metadata formats.
     *
     * @return void
     */
    protected function useDatabase(): void
    {
        if (!isset(self::getSingletons(Configuration::class)[Configuration::class])) {
            $this->useConfiguration();
        }
        self::setSingleton(EntityManager::class, null);
        $this->em = EntityManager::getInstance(new ArrayAdapter());
        if (PHP_VERSION_ID < 80400 && !self::$proxiesGenerated) {
            // Without native lazy objects, Doctrine needs pre-generated proxy classes.
            $proxyDir = (string) $this->em->getConfiguration()->getProxyDir();
            if (!is_dir($proxyDir)) {
                mkdir($proxyDir, 0775, true);
            }
            $this->em->getProxyFactory()->generateProxyClasses(
                $this->em->getMetadataFactory()->getAllMetadata(),
                $proxyDir
            );
            self::$proxiesGenerated = true;
        }
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        /** @var array<string, array{namespace: string, schema: string}> */
        $formats = Configuration::getInstance()->metadataPrefix;
        foreach ($formats as $prefix => $format) {
            $this->em->addOrUpdate(new Format($prefix, $format['namespace'], $format['schema']));
        }
    }

    /**
     * Add a record to the test database.
     *
     * @param string $identifier The record identifier
     * @param ?string $content The record content or NULL for a deleted record
     * @param string $lastChanged The datestamp of last change
     * @param string[] $sets The set specs (non-existing sets are created)
     * @param string $format The metadata prefix
     *
     * @return void
     */
    protected function addRecord(
        string $identifier,
        ?string $content = self::CONTENT,
        string $lastChanged = '2026-01-01T00:00:00Z',
        array $sets = [],
        string $format = 'oai_dc'
    ): void {
        $metadataFormat = $this->em->getMetadataFormat($format);
        $this->assertNotNull($metadataFormat, sprintf('Metadata format "%s" does not exist.', $format));
        $record = new Record($identifier, $metadataFormat, $content, new DateTime($lastChanged));
        foreach ($sets as $spec) {
            $set = $this->em->getSet($spec);
            if (!isset($set)) {
                $set = new Set($spec);
                $this->em->persist($set);
            }
            $record->addSet($set);
        }
        $this->em->addOrUpdate($record);
        $this->em->getConfiguration()->getResultCache()?->clear();
    }

    /**
     * Add a set to the test database.
     *
     * @param string $spec The set spec
     * @param ?string $name The set name
     * @param ?string $description The set description
     *
     * @return void
     */
    protected function addSet(string $spec, ?string $name = null, ?string $description = null): void
    {
        $this->em->addOrUpdate(new Set($spec, $name, $description));
        $this->em->getConfiguration()->getResultCache()?->clear();
    }

    /**
     * Create a temporary file which is removed after the test.
     *
     * @param string $content The file content
     *
     * @return string The file path
     */
    protected function createTempFile(string $content): string
    {
        $file = tempnam(sys_get_temp_dir(), 'oai-pmh2-test-');
        $this->assertIsString($file);
        file_put_contents($file, $content);
        $this->tempFiles[] = $file;
        return $file;
    }

    /**
     * Send an OAI-PMH request to the data provider.
     *
     * @param string $query The raw query string (GET) or request body (POST)
     * @param string $method The HTTP method
     * @param array<string, string> $headers The HTTP request headers
     * @param string $uri The request URI (without query string)
     *
     * @return ResponseInterface The HTTP response
     */
    protected function request(
        string $query,
        string $method = 'GET',
        array $headers = [],
        string $uri = 'http://example.org/oai'
    ): ResponseInterface {
        // Every request is processed by a new PHP process in production, so reset the per-request state.
        self::setSingleton(ErrorHandler::class, null);
        self::setSingleton(MiddlewareQueue::class, null);
        $this->em->clear();
        if ($method === 'GET') {
            $request = new ServerRequest('GET', $uri . ($query !== '' ? '?' . $query : ''));
        } else {
            $request = new ServerRequest($method, $uri, $headers, $query);
        }
        $response = (new QueueRequestHandler([new Dispatcher()]))->handle($request);
        // Exceptions are converted into "500 Internal Server Error" responses with a "Warning" header.
        $this->assertFalse($response->hasHeader('Warning'), (string) $response->getBody());
        $this->assertSame(200, $response->getStatusCode());
        return $response;
    }

    /**
     * Send an OAI-PMH request and return the response document for querying.
     *
     * The namespace prefix "oai" is registered for OAI-PMH elements.
     *
     * @param string $query The raw query string (GET) or request body (POST)
     * @param string $method The HTTP method
     * @param array<string, string> $headers The HTTP request headers
     * @param string $uri The request URI (without query string)
     *
     * @return DOMXPath The response document
     */
    protected function requestXml(
        string $query,
        string $method = 'GET',
        array $headers = [],
        string $uri = 'http://example.org/oai'
    ): DOMXPath {
        $response = $this->request($query, $method, $headers, $uri);
        $this->assertSame('text/xml', $response->getHeaderLine('Content-Type'));
        $document = new DOMDocument();
        $this->assertTrue($document->loadXML((string) $response->getBody()));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('oai', self::OAI_NAMESPACE);
        return $xpath;
    }

    /**
     * Get the text content of all nodes matching an XPath expression.
     *
     * @param DOMXPath $xpath The response document
     * @param string $expression The XPath expression
     *
     * @return list<string> The text contents
     */
    protected static function texts(DOMXPath $xpath, string $expression): array
    {
        $texts = [];
        $nodes = $xpath->query($expression);
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                $texts[] = $node->textContent;
            }
        }
        return $texts;
    }

    /**
     * Get the text content of the first node matching an XPath expression.
     *
     * @param DOMXPath $xpath The response document
     * @param string $expression The XPath expression
     *
     * @return ?string The text content or NULL if there is no such node
     */
    protected static function text(DOMXPath $xpath, string $expression): ?string
    {
        return self::texts($xpath, $expression)[0] ?? null;
    }

    /**
     * Get the output of a console command with normalized whitespace (long messages are wrapped).
     *
     * @param CommandTester $tester The command tester
     *
     * @return string The command output
     */
    protected static function display(CommandTester $tester): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $tester->getDisplay()));
    }

    /**
     * Get the OAI-PMH error codes of a response.
     *
     * @param DOMXPath $xpath The response document
     *
     * @return list<string> The error codes
     */
    protected static function errors(DOMXPath $xpath): array
    {
        return self::texts($xpath, '/oai:OAI-PMH/oai:error/@code');
    }

    /**
     * Assert that a response has exactly the given OAI-PMH error.
     *
     * @param string $code The expected error code
     * @param DOMXPath $xpath The response document
     *
     * @return void
     */
    protected function assertOaiError(string $code, DOMXPath $xpath): void
    {
        $this->assertSame([$code], self::errors($xpath));
    }

    /**
     * Get all instances of a singleton class.
     *
     * @param class-string $class The singleton class
     *
     * @return array<class-string, object> The instances
     */
    private static function getSingletons(string $class): array
    {
        /** @var array<class-string, object> */
        return (new ReflectionProperty($class, '_singleton'))->getValue();
    }

    /**
     * Replace (or with NULL remove) the instance of a singleton class.
     *
     * @param class-string $class The singleton class
     * @param ?object $instance The new instance or NULL to remove it
     *
     * @return void
     */
    protected static function setSingleton(string $class, ?object $instance): void
    {
        $singletons = self::getSingletons($class);
        if (isset($instance)) {
            $singletons[$class] = $instance;
        } else {
            unset($singletons[$class]);
        }
        (new ReflectionProperty($class, '_singleton'))->setValue(null, $singletons);
    }

    /**
     * Close the test database, reset all singletons and remove temporary files.
     *
     * @return void
     */
    #[Override]
    protected function tearDown(): void
    {
        if (isset($this->em)) {
            $this->em->getConnection()->close();
        }
        foreach ([EntityManager::class, Configuration::class, ErrorHandler::class, MiddlewareQueue::class] as $class) {
            self::setSingleton($class, null);
        }
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }
}
