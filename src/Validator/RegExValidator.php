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
 * Validator for Regular Expressions.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 */
class RegExValidator
{
    /**
     * Get constraints for regular expression.
     *
     * @param string $regEx The regular expression for validation
     *
     * @return array<Constraint> The array of constraints
     */
    protected static function getValidationConstraints(string $regEx): array
    {
        return [
            new Assert\Regex(
                pattern: [
                    'pattern' => $regEx,
                    'message' => 'This value does not match the regular expression "{{ pattern }}".'
                ]
            )
        ];
    }

    /**
     * Check if a string matches a given regular expression.
     *
     * @param string $string The string
     * @param string $regEx The regular expression
     *
     * @return ConstraintViolationListInterface The list of violations
     */
    public static function validate(string $string, string $regEx): ConstraintViolationListInterface
    {
        return Validation::createValidator()->validate(
            value: $string,
            constraints: self::getValidationConstraints(regEx: $regEx)
        );
    }
}
