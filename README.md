# OAI-PMH2 Data Provider

***A stand-alone, easy to maintain application for providing a data service following the [Open Archives Initiative Protocol for Metadata Harvesting 2.0 (OAI-PMH2)](https://openarchives.org/OAI/openarchivesprotocol.html).***

The OAI-PMH2 Data Provider serves records in multiple XML formats from any SQL database. It supports persistent deletion policies by transparently keeping track of deleted records, can manage hierarchical sets with descriptions and uses resumption tokens for flow control.

This application follows the highest coding standards of [PHPStan](https://phpstan.org/), [Psalm](https://psalm.dev/), [PHP Mess Detector](https://phpmd.org/), [PHP_CodeSniffer](https://github.com/PHPCSStandards/PHP_CodeSniffer/), and complies to [PSR-12](https://www.php-fig.org/psr/psr-12/) code style guidelines to make sure it is reliable, maintainable and easily reusable.

## Quick Start

The intended and recommended way of installing this application is via [Composer](https://getcomposer.org/). The following command will get you the latest version:

```shell
composer create-project opencultureconsulting/oai-pmh2 --ask --no-dev
```

All available versions as well as further information about requirements and dependencies can be found on [Packagist](https://packagist.org/packages/opencultureconsulting/oai-pmh2).

## Full Documentation

The full documentation is available on [GitHub Pages](https://code.opencultureconsulting.com/oai-pmh2/) or alternatively in [doc/](doc/).

## Quality Gates

[![PHPCS](https://github.com/opencultureconsulting/oai-pmh2/actions/workflows/phpcs.yml/badge.svg)](https://github.com/opencultureconsulting/oai-pmh2/actions/workflows/phpcs.yml)
[![PHPMD](https://github.com/opencultureconsulting/oai-pmh2/actions/workflows/phpmd.yml/badge.svg)](https://github.com/opencultureconsulting/oai-pmh2/actions/workflows/phpmd.yml)

[![PHPStan](https://github.com/opencultureconsulting/oai-pmh2/actions/workflows/phpstan.yml/badge.svg)](https://github.com/opencultureconsulting/oai-pmh2/actions/workflows/phpstan.yml)
[![Psalm](https://github.com/opencultureconsulting/oai-pmh2/actions/workflows/psalm.yml/badge.svg)](https://github.com/opencultureconsulting/oai-pmh2/actions/workflows/psalm.yml)
