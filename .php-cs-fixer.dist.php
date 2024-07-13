<?php

/**
 * PHP Basics
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

namespace PhpCsFixer;

/**
 * Configuration for PHP-CS-Fixer.
 *
 * @see https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/blob/master/doc/config.rst
 *
 * @return ConfigInterface
 */
$config = new Config();
$finder = new Finder();

return $config
    ->setRiskyAllowed(true)
    ->setRules(['@PSR12' => true])
    ->setFinder($finder->in([__DIR__ . '/src']));
