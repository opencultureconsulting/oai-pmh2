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

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;

/**
 * Validator for configuration settings.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 */
class ConfigurationValidator
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
                        new Assert\NotBlank()
                    ],
                    'adminEmail' => [
                        new Assert\Type(type: 'string'),
                        new Assert\Email(options: ['mode' => 'html5']),
                        new Assert\NotBlank()
                    ],
                    'database' => [
                        new Assert\Type(type: 'string'),
                        new Assert\NotBlank()
                    ],
                    'metadataPrefix' => [
                        new Assert\Type(type: 'array'),
                        new Assert\All(
                            constraints: [
                                new Assert\Collection(
                                    fields: [
                                        'schema' => [
                                            new Assert\Type(type: 'string'),
                                            new Assert\Url(),
                                            new Assert\NotBlank()
                                        ],
                                        'namespace' => [
                                            new Assert\Type(type: 'string'),
                                            new Assert\Url(),
                                            new Assert\NotBlank()
                                        ]
                                    ]
                                )
                            ]
                        )
                    ],
                    'deletedRecords' => [
                        new Assert\Type(type: 'string'),
                        new Assert\Choice(options: ['no', 'persistent', 'transient']),
                        new Assert\NotBlank()
                    ],
                    'maxRecords' => [
                        new Assert\Type(type: 'int'),
                        new Assert\Range(options: ['min' => 1, 'max' => 100])
                    ],
                    'tokenValid' => [
                        new Assert\Type(type: 'int'),
                        new Assert\Range(options: ['min' => 300, 'max' => 86400])
                    ]
                ]
            )
        ];
    }

    /**
     * Validate the given configuration array.
     *
     * @param array<array-key, mixed> $config The configuration array to validate
     *
     * @return ConstraintViolationListInterface The list of violations
     */
    public static function validate(array $config): ConstraintViolationListInterface
    {
        return Validation::createValidator()->validate(
            value: $config,
            constraints: self::getValidationConstraints()
        );
    }
}
