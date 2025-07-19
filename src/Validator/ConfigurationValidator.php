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
            new Assert\Collection([
                'repositoryName' => [
                    new Assert\Type('string'),
                    new Assert\NotBlank()
                ],
                'adminEmail' => [
                    new Assert\Type('string'),
                    new Assert\Email(['mode' => 'html5']),
                    new Assert\NotBlank()
                ],
                'database' => [
                    new Assert\Type('string'),
                    new Assert\NotBlank()
                ],
                'metadataPrefix' => [
                    new Assert\Type('array'),
                    new Assert\All([
                        new Assert\Collection([
                            'schema' => [
                                new Assert\Type('string'),
                                new Assert\Url(),
                                new Assert\NotBlank()
                            ],
                            'namespace' => [
                                new Assert\Type('string'),
                                new Assert\Url(),
                                new Assert\NotBlank()
                            ]
                        ])
                    ])
                ],
                'deletedRecords' => [
                    new Assert\Type('string'),
                    new Assert\Choice(['no', 'persistent', 'transient']),
                    new Assert\NotBlank()
                ],
                'maxRecords' => [
                    new Assert\Type('int'),
                    new Assert\Range(['min' => 1, 'max' => 100])
                ],
                'tokenValid' => [
                    new Assert\Type('int'),
                    new Assert\Range(['min' => 300, 'max' => 86400])
                ],
                'batchSize' => [
                    new Assert\Type('int'),
                    new Assert\PositiveOrZero()
                ]
            ])
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
            $config,
            self::getValidationConstraints()
        );
    }
}
