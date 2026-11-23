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

namespace OCC\OaiPmh2\Tests\Validator;

use OCC\OaiPmh2\Tests\TestCase;
use OCC\OaiPmh2\Validator\ConfigurationValidator;
use OCC\OaiPmh2\Validator\RegExValidator;
use OCC\OaiPmh2\Validator\UrlValidator;
use OCC\OaiPmh2\Validator\XmlValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Yaml\Yaml;

#[CoversClass(ConfigurationValidator::class)]
#[CoversClass(RegExValidator::class)]
#[CoversClass(UrlValidator::class)]
#[CoversClass(XmlValidator::class)]
final class ValidatorTest extends TestCase
{
    #[Test]
    public function theDefaultConfigurationIsValid(): void
    {
        $config = (array) Yaml::parseFile(__DIR__ . '/../../config/config.dist.yml');
        $config['maintenanceMode'] = false;

        ConfigurationValidator::validate($config);

        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function invalidSettingProvider(): array
    {
        return [
            'empty repository name' => ['repositoryName', ' '],
            'invalid admin email' => ['adminEmail', 'admin'],
            'empty database' => ['database', ''],
            'invalid metadata prefix' => ['metadataPrefix', ['oai dc' => ['namespace' => 'http://example.org/', 'schema' => 'http://example.org/s.xsd']]],
            'missing schema' => ['metadataPrefix', ['oai_dc' => ['namespace' => 'http://example.org/']]],
            'invalid namespace' => ['metadataPrefix', ['oai_dc' => ['namespace' => 'example', 'schema' => 'http://example.org/s.xsd']]],
            'unknown deleted records policy' => ['deletedRecords', 'sometimes'],
            'too few records' => ['maxRecords', 0],
            'too many records' => ['maxRecords', 101],
            'records as string' => ['maxRecords', '50'],
            'token validity too short' => ['tokenValid', 299],
            'token validity too long' => ['tokenValid', 86401],
            'maintenance mode as string' => ['maintenanceMode', 'yes']
        ];
    }

    #[Test]
    #[DataProvider('invalidSettingProvider')]
    public function itRejectsInvalidSettings(string $setting, mixed $value): void
    {
        $config = self::SETTINGS;
        $config[$setting] = $value;

        $this->expectException(ValidationFailedException::class);

        ConfigurationValidator::validate($config);
    }

    #[Test]
    public function itRejectsMissingAndUnknownSettings(): void
    {
        $missing = self::SETTINGS;
        unset($missing['adminEmail']);
        $unknown = self::SETTINGS;
        $unknown['batchSize'] = 100;

        foreach ([$missing, $unknown] as $config) {
            try {
                ConfigurationValidator::validate($config);
                $this->fail('Invalid configuration was accepted.');
            } catch (ValidationFailedException $exception) {
                $this->assertGreaterThan(0, count($exception->getViolations()));
            }
        }
    }

    #[Test]
    public function itRedactsTheDatabaseConnectionInErrors(): void
    {
        $config = self::SETTINGS;
        $config['database'] = 'mysql://user:secret@localhost/oai';
        $config['maxRecords'] = 0;

        try {
            ConfigurationValidator::validate($config);
            $this->fail('Invalid configuration was accepted.');
        } catch (ValidationFailedException $exception) {
            $value = $exception->getValue();
            $this->assertIsArray($value);
            $this->assertSame('<redacted>', $value['database']);
            $this->assertStringNotContainsString('secret', print_r($value, true));
        }
    }

    #[Test]
    public function itValidatesXml(): void
    {
        $this->assertCount(0, XmlValidator::validate(self::CONTENT));
        $this->assertGreaterThan(0, count(XmlValidator::validate('<not>well-formed')));
        $this->assertGreaterThan(0, count(XmlValidator::validate('no XML at all')));
        $this->assertGreaterThan(0, count(XmlValidator::validate('  ')));
    }

    #[Test]
    public function itValidatesUrls(): void
    {
        $this->assertCount(0, UrlValidator::validate('https://example.org/schema.xsd'));
        $this->assertGreaterThan(0, count(UrlValidator::validate('example')));
        $this->assertGreaterThan(0, count(UrlValidator::validate('')));
    }

    #[Test]
    public function itValidatesRegularExpressions(): void
    {
        $this->assertCount(0, RegExValidator::validate('abc', '/^[a-z]+$/'));
        $this->assertGreaterThan(0, count(RegExValidator::validate('ABC', '/^[a-z]+$/')));
    }
}
