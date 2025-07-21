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
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;

/**
 * Validator for XML.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 */
final class XmlValidator
{
    /**
     * Get constraints for XML.
     *
     * @return array<Constraint> The array of constraints
     */
    protected static function getValidationConstraints(): array
    {
        return [
            new Assert\Type('string'),
            new Assert\NotBlank(['normalizer' => 'trim'])
        ];
    }

    /**
     * Check if the given string is valid XML.
     *
     * @param string $xml The XML string
     *
     * @return ConstraintViolationListInterface The list of violations
     */
    public static function validate(string $xml): ConstraintViolationListInterface
    {
        $violations = Validation::createValidator()->validate(
            $xml,
            self::getValidationConstraints()
        );
        if (simplexml_load_string($xml) === false) {
            $violations->add(
                new ConstraintViolation(
                    'Value could not be parsed as XML.',
                    'Value could not be parsed as XML.',
                    [],
                    $xml,
                    null,
                    $xml
                )
            );
        }
        return $violations;
    }
}
