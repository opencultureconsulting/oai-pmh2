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

namespace OCC\OaiPmh2;

use OCC\OaiPmh2\Validator\RegExValidator;
use OCC\OaiPmh2\Validator\UrlValidator;
use OCC\OaiPmh2\Validator\XmlValidator;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Base class for all Doctrine/ORM entities.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 */
abstract class Entity
{
    /**
     * Check if a string is a valid URL.
     *
     * @param string $url The URL
     *
     * @return string The validated URL
     *
     * @throws ValidationFailedException
     */
    protected function validateUrl(string $url): string
    {
        $url = trim($url);
        $violations = UrlValidator::validate($url);
        if ($violations->count() > 0) {
            throw new ValidationFailedException(null, $violations);
        }
        return $url;
    }

    /**
     * Check if a string matches a given regular expression.
     *
     * @param string $string The string
     * @param string $regEx The regular expression
     *
     * @return string The validated string
     *
     * @throws ValidationFailedException
     */
    protected function validateRegEx(string $string, string $regEx): string
    {
        $violations = RegExValidator::validate($string, $regEx);
        if ($violations->count() > 0) {
            throw new ValidationFailedException(null, $violations);
        }
        return $string;
    }

    /**
     * Check if a string is well-formed XML.
     *
     * @param string $xml The XML string
     *
     * @return string The validated XML string
     *
     * @throws ValidationFailedException
     */
    protected function validateXml(string $xml): string
    {
        $violations = XmlValidator::validate($xml);
        if ($violations->count() > 0) {
            throw new ValidationFailedException(null, $violations);
        }
        return $xml;
    }
}
