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

namespace OCC\OaiPmh2\Tests\Entity;

use DateTime;
use OCC\OaiPmh2\Entity;
use OCC\OaiPmh2\Entity\Format;
use OCC\OaiPmh2\Entity\Set;
use OCC\OaiPmh2\Entity\Token;
use OCC\OaiPmh2\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[CoversClass(Set::class)]
#[CoversClass(Format::class)]
#[CoversClass(Token::class)]
#[CoversClass(Entity::class)]
final class SetAndFormatTest extends TestCase
{
    #[Test]
    public function aSetHoldsItsData(): void
    {
        $description = '<description xmlns="urn:x">About</description>';
        $set = new Set('coll:a', 'Collection A', " $description ");

        $this->assertSame('coll:a', $set->getSpec());
        $this->assertSame('coll:a', (string) $set);
        $this->assertSame('Collection A', $set->getName());
        $this->assertTrue($set->hasDescription());
        $this->assertSame($description, $set->getDescription());
        $this->assertTrue($set->isEmpty());
    }

    #[Test]
    public function aSetUsesItsSpecAsDefaultName(): void
    {
        $set = new Set('coll');

        $this->assertSame('coll', $set->getName());
        $this->assertFalse($set->hasDescription());
        $this->assertNull($set->getDescription());

        $set->setName('Collection');
        $set->setName(null);

        $this->assertSame('coll', $set->getName());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidSpecProvider(): array
    {
        return [
            'empty' => [''],
            'whitespace' => ['my set'],
            'empty level' => ['coll::a'],
            'trailing colon' => ['coll:'],
            'invalid character' => ['coll/a']
        ];
    }

    #[Test]
    #[DataProvider('invalidSpecProvider')]
    public function aSetRejectsInvalidSpecs(string $spec): void
    {
        $this->expectException(ValidationFailedException::class);

        new Set($spec);
    }

    #[Test]
    public function aSetRejectsInvalidDescriptions(): void
    {
        $this->expectException(ValidationFailedException::class);

        new Set('coll', null, 'no XML');
    }

    #[Test]
    public function aFormatHoldsItsData(): void
    {
        $format = new Format('oai_dc', ' http://www.openarchives.org/OAI/2.0/oai_dc/ ', 'https://example.org/oai_dc.xsd');

        $this->assertSame('oai_dc', $format->getPrefix());
        $this->assertSame('http://www.openarchives.org/OAI/2.0/oai_dc/', $format->getNamespace());
        $this->assertSame('https://example.org/oai_dc.xsd', $format->getSchema());
        $this->assertCount(0, $format->getRecords());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function invalidFormatProvider(): array
    {
        return [
            'empty prefix' => ['', 'http://example.org/ns', 'http://example.org/schema.xsd'],
            'too long prefix' => ['abcdefghijklmnopq', 'http://example.org/ns', 'http://example.org/schema.xsd'],
            'invalid prefix' => ['oai dc', 'http://example.org/ns', 'http://example.org/schema.xsd'],
            'invalid namespace' => ['oai_dc', 'no URL', 'http://example.org/schema.xsd'],
            'invalid schema' => ['oai_dc', 'http://example.org/ns', '']
        ];
    }

    #[Test]
    #[DataProvider('invalidFormatProvider')]
    public function aFormatRejectsInvalidData(string $prefix, string $namespace, string $schema): void
    {
        $this->expectException(ValidationFailedException::class);

        new Format($prefix, $namespace, $schema);
    }

    #[Test]
    public function aTokenIsValidForTheConfiguredTime(): void
    {
        $this->useConfiguration(['tokenValid' => 600]);
        $parameters = ['verb' => 'ListSets', 'counter' => 1, 'completeListSize' => 5];

        $token = new Token('ListSets', $parameters);

        $this->assertMatchesRegularExpression('/^[0-9a-z]{8}$/', $token->getToken());
        $this->assertSame('ListSets', $token->getVerb());
        $this->assertSame($parameters, $token->getParameters());
        $this->assertEqualsWithDelta((new DateTime())->getTimestamp() + 600, $token->getValidUntil()->getTimestamp(), 5);
    }

    #[Test]
    public function tokensCreatedInQuickSuccessionAreUnique(): void
    {
        $this->useConfiguration([]);
        $parameters = ['verb' => 'ListSets', 'counter' => 1, 'completeListSize' => 5];
        $tokens = [];

        for ($i = 0; $i < 1000; $i++) {
            $tokens[] = (new Token('ListSets', $parameters))->getToken();
        }

        $this->assertCount(1000, array_unique($tokens));
    }
}
