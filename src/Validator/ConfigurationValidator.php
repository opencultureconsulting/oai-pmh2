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

namespace OCC\OaiPmh2\Validator;

use OCC\OaiPmh2\Configuration;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validation;

/**
 * Validator for configuration settings.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 *
 * @phpstan-import-type ConfigArray from Configuration
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
final class ConfigurationValidator
{
    /**
     * Get constraints for configuration array.
     *
     * @return array<Constraint> The collection of constraints
     */
    protected static function getValidationConstraints(): array
    {
        return [
            new Assert\Collection(
                fields: [
                    'repositoryName' => [
                        new Assert\Type(type: 'string'),
                        new Assert\NotBlank(normalizer: 'trim')
                    ],
                    'adminEmail' => [
                        new Assert\Type(type: 'string'),
                        new Assert\Email(mode: 'html5'),
                        new Assert\NotBlank(normalizer: 'trim')
                    ],
                    'database' => [
                        new Assert\Type(type: 'string'),
                        new Assert\NotBlank(normalizer: 'trim')
                    ],
                    'metadataPrefix' => [
                        new Assert\Type(type: 'array'),
                        new Assert\Callback([self::class, 'validateMetadataPrefix']),
                        new Assert\All([
                            new Assert\Collection(
                                fields: [
                                    'schema' => [
                                        new Assert\Type('string'),
                                        new Assert\Length(max: 255),
                                        new Assert\Url(),
                                        new Assert\NotBlank(normalizer: 'trim')
                                    ],
                                    'namespace' => [
                                        new Assert\Type('string'),
                                        new Assert\Length(max: 255),
                                        new Assert\Url(),
                                        new Assert\NotBlank(normalizer: 'trim')
                                    ]
                                ],
                                allowExtraFields: false,
                                allowMissingFields: false
                            )
                        ])
                    ],
                    'deletedRecords' => [
                        new Assert\Type(type: 'string'),
                        new Assert\Choice(choices: ['no', 'persistent', 'transient'])
                    ],
                    'maxRecords' => [
                        new Assert\Type(type: 'int'),
                        new Assert\Range(min: 1, max: 100)
                    ],
                    'tokenValid' => [
                        new Assert\Type(type: 'int'),
                        new Assert\Range(min: 300, max: 86400)
                    ],
                    'batchSize' => [
                        new Assert\Type(type: 'int'),
                        new Assert\PositiveOrZero()
                    ],
                    'autoSets' => [
                        new Assert\Type(type: 'bool')
                    ]
                ],
                allowExtraFields: false,
                allowMissingFields: false
            )
        ];
    }

    /**
     * Validate the given configuration array.
     *
     * @param mixed[] $config The configuration array to validate
     *
     * @return void
     *
     * @throws ValidationFailedException if validation fails
     *
     * @phpstan-assert ConfigArray $config
     */
    public static function validate(array $config): void
    {
        $violations = Validation::createValidator()->validate(
            $config,
            self::getValidationConstraints()
        );
        if (count($violations) > 0) {
            // Redact sensitive information like database connection strings
            if (isset($config['database'])) {
                $config['database'] = '<redacted>';
            }
            throw new ValidationFailedException($config, $violations);
        }
    }

    /**
     * Validate metadata prefixes.
     *
     * @param array<string, string[]> $value Configuration array of metadata prefixes
     * @param ExecutionContextInterface $context The execution context for validation
     *
     * @return void
     */
    public static function validateMetadataPrefix(mixed $value, ExecutionContextInterface $context): void
    {
        foreach (array_keys($value) as $key) {
            if (!(preg_match('/^[A-Za-z0-9\-_\.!~\*\'\(\)]{1,16}$/', $key) > 0)) {
                $context->buildViolation(
                    sprintf(
                        'Invalid metadata prefix "%s"',
                        $key
                    )
                )->atPath('metadataPrefix')->addViolation();
            }
        }
    }
}
