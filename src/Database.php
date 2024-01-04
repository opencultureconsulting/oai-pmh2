<?php

/**
 * OAI-PMH 2.0 Data Provider
 * Copyright (C) 2023 Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
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

namespace OCC\OaiPmh2;

use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Configuration as DoctrineConfiguration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Proxy\ProxyFactory;
use Doctrine\ORM\Tools\Pagination\Paginator;
use OCC\Basics\Traits\Singleton;
use OCC\OaiPmh2\Database\Format;
use OCC\OaiPmh2\Database\Record;
use OCC\OaiPmh2\Database\Result;
use OCC\OaiPmh2\Database\Set;
use OCC\OaiPmh2\Database\Token;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Handles all database shenanigans.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package opencultureconsulting/oai-pmh2
 *
 * @template Formats of array<string, Format>
 * @template Records of array<string, Record>
 * @template Sets of array<string, Set>
 */
class Database
{
    use Singleton;

    protected const DB_TABLES = [
        'formats',
        'records',
        'records_sets',
        'sets',
        'tokens'
    ];

    /**
     * This holds the Doctrine entity manager.
     */
    protected EntityManager $entityManager;

    /**
     * Add or update metadata format.
     *
     * @param string $prefix The metadata prefix
     * @param string $namespace The namespace URI
     * @param string $schema The schema URL
     *
     * @return void
     *
     * @throws ValidationFailedException
     */
    public function addOrUpdateMetadataFormat(string $prefix, string $namespace, string $schema): void
    {
        $format = $this->entityManager->find(Format::class, $prefix);
        if (isset($format)) {
            try {
                $format->setNamespace($namespace);
                $format->setSchema($schema);
            } catch (ValidationFailedException $exception) {
                throw $exception;
            }
        } else {
            try {
                $format = new Format($prefix, $namespace, $schema);
            } catch (ValidationFailedException $exception) {
                throw $exception;
            }
        }
        $this->entityManager->persist($format);
        $this->entityManager->flush();
    }

    /**
     * Add or update record.
     *
     * @param string $identifier The record identifier
     * @param Format|string $format The metadata prefix
     * @param ?string $data The record's content
     * @param ?DateTime $lastChanged The date of last change
     * @param ?array<string, Set> $sets The record's associated sets
     * @param bool $bulkMode Should we operate in bulk mode (no flush)?
     *
     * @return void
     */
    public function addOrUpdateRecord(
        string $identifier,
        Format|string $format,
        ?string $data = null,
        ?DateTime $lastChanged = null,
        // TODO: Complete support for sets
        ?array $sets,
        bool $bulkMode = false
    ): void
    {
        if (!$format instanceof Format) {
            /** @var Format */
            $format = $this->entityManager->getReference(Format::class, $format);
        }
        $record = $this->entityManager->find(Record::class, ['identifier' => $identifier, 'format' => $format]);
        if (isset($record)) {
            try {
                $record->setContent($data);
                $record->setLastChanged($lastChanged);
            } catch (ValidationFailedException $exception) {
                throw $exception;
            }
        } else {
            try {
                $record = new Record($identifier, $format, $data, $lastChanged);
            } catch (ValidationFailedException $exception) {
                throw $exception;
            }
        }
        $this->entityManager->persist($record);
        if (!$bulkMode) {
            $this->entityManager->flush();
        }
    }

    /**
     * Flush all changes to the database.
     *
     * @param bool $clear Also clear the entity manager?
     *
     * @return void
     */
    public function flush(bool $clear = false): void
    {
        $this->entityManager->flush();
        if ($clear) {
            $this->entityManager->clear();
        }
    }

    /**
     * Get the earliest datestamp of any record.
     *
     * @return string The earliest datestamp
     */
    public function getEarliestDatestamp(): string
    {
        $timestamp = '0000-00-00T00:00:00Z';
        $dql = $this->entityManager->createQueryBuilder();
        $dql->select('record')
            ->from(Record::class, 'record')
            ->orderBy('record.lastChanged', 'ASC')
            ->setMaxResults(1);
        $query = $dql->getQuery();
        $query->enableResultCache();
        /** @var ?array<string, \DateTime> */
        $result = $query->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY);
        if (isset($result)) {
            $timestamp = $result['lastChanged']->format('Y-m-d\TH:i:s\Z');
        }
        return $timestamp;
    }

    /**
     * Get the Doctrine entity manager.
     *
     * @return EntityManager The entity manager instance
     */
    public function getEntityManager(): EntityManager
    {
        return $this->entityManager;
    }

    /**
     * Get all metadata prefixes.
     *
     * @param ?string $identifier Optional record identifier
     *
     * @return Result<Formats> The metadata prefixes
     */
    public function getMetadataFormats(?string $identifier = null): Result
    {
        $dql = $this->entityManager->createQueryBuilder();
        $dql->select('format')
            ->from(Format::class, 'format', 'format.prefix');
        if (isset($identifier)) {
            $dql->innerJoin(
                    'format.records',
                    'record',
                    'WITH',
                    $dql->expr()->andX(
                        $dql->expr()->eq('record.identifier', ':identifier'),
                        $dql->expr()->isNotNull('record.content')
                    )
                )
                ->setParameter('identifier', $identifier);
        }
        $query = $dql->getQuery();
        $query->enableResultCache();
        /** @var Formats */
        $queryResult = $query->getResult();
        return new Result($queryResult);
    }

    /**
     * Get a single record.
     *
     * @param string $identifier The record identifier
     * @param string $metadataPrefix The metadata prefix
     *
     * @return ?Record The record or NULL on failure
     */
    public function getRecord(string $identifier, string $metadataPrefix): ?Record
    {
        return $this->entityManager->find(
            Record::class,
            [
                'identifier' => $identifier,
                'format' => $metadataPrefix
            ]
        );
    }

    /**
     * Get list of records.
     *
     * @param string $verb The currently requested verb ('ListIdentifiers' or 'ListRecords')
     * @param string $metadataPrefix The metadata prefix
     * @param int $counter Counter for split result sets
     * @param ?string $from The "from" datestamp
     * @param ?string $until The "until" datestamp
     * @param ?string $set The set spec
     *
     * @return Result<Records> The records and possibly a resumtion token
     */
    public function getRecords(
        string $verb,
        string $metadataPrefix,
        int $counter = 0,
        ?string $from = null,
        ?string $until = null,
        ?string $set = null
    ): Result
    {
        $maxRecords = Configuration::getInstance()->maxRecords;
        $cursor = $counter * $maxRecords;

        $dql = $this->entityManager->createQueryBuilder();
        $dql->select('record')
            ->from(Record::class, 'record', 'record.identifier')
            ->where($dql->expr()->eq('record.format', ':metadataPrefix'))
            ->setParameter('metadataPrefix', $metadataPrefix)
            ->setFirstResult($cursor)
            ->setMaxResults($maxRecords);
        if (isset($from)) {
            $dql->andWhere($dql->expr()->gte('record.lastChanged', ':from'));
            $dql->setParameter('from', new DateTime($from));
        }
        if (isset($until)) {
            $dql->andWhere($dql->expr()->lte('record.lastChanged', ':until'));
            $dql->setParameter('until', new DateTime($until));
        }
        if (isset($set)) {
            $dql->andWhere($dql->expr()->in('record.sets', ':set'));
            $dql->setParameter('set', $set);
        }
        $query = $dql->getQuery();
        /** @var Records */
        $queryResult = $query->getResult();
        $result = new Result($queryResult);
        $paginator = new Paginator($query, true);
        if (count($paginator) > ($cursor + count($result))) {
            $token = new Token($verb, [
                'counter' => $counter + 1,
                'completeListSize' => count($paginator),
                'metadataPrefix' => $metadataPrefix,
                'from' => $from,
                'until' => $until,
                'set' => $set
            ]);
            $this->entityManager->persist($token);
            $this->entityManager->flush();
            $result->setResumptionToken($token);
        }
        return $result;
    }

    /**
     * Get resumption token.
     *
     * @param string $token The token
     * @param string $verb The current verb to validate token
     *
     * @return ?Token The resumption token or NULL if invalid
     */
    public function getResumptionToken(string $token, string $verb): ?Token
    {
        $dql = $this->entityManager->createQueryBuilder();
        $dql->select('token')
            ->from(Token::class, 'token')
            ->where($dql->expr()->gte('token.validUntil', ':now'))
            ->andWhere($dql->expr()->eq('token.token', ':token'))
            ->andWhere($dql->expr()->eq('token.verb', ':verb'))
            ->setParameter('now', new DateTime())
            ->setParameter('token', $token)
            ->setParameter('verb', $verb)
            ->setMaxResults(1);
        $query = $dql->getQuery();
        /** @var ?Token */
        return $query->getOneOrNullResult();
    }

    /**
     * Get all sets.
     *
     * @param int $counter Counter for split result sets
     *
     * @return Result<Sets> The sets and possibly a resumption token
     */
    public function getSets($counter = 0): Result
    {
        $result = [];
        $maxRecords = Configuration::getInstance()->maxRecords;
        $cursor = $counter * $maxRecords;

        $dql = $this->entityManager->createQueryBuilder();
        $dql->select('sets')
            ->from(Set::class, 'sets', 'sets.spec')
            ->setFirstResult($cursor)
            ->setMaxResults($maxRecords);
        $query = $dql->getQuery();
        $query->enableResultCache();
        /** @var Sets */
        $resultQuery = $query->getResult();
        $result = new Result($resultQuery);
        $paginator = new Paginator($query, false);
        if (count($paginator) > ($cursor + count($result))) {
            $token = new Token('ListSets', [
                'counter' => $counter + 1,
                'completeListSize' => count($paginator)
            ]);
            $this->entityManager->persist($token);
            $this->entityManager->flush();
            $result->setResumptionToken($token);
        }
        return $result;
    }

    /**
     * Check if a record identifier exists.
     *
     * @param string $identifier The record identifier
     *
     * @return bool Whether the identifier exists
     */
    public function idDoesExist(string $identifier): bool
    {
        $dql = $this->entityManager->createQueryBuilder();
        $dql->select('COUNT(record.identifier)')
            ->from(Record::class, 'record')
            ->where($dql->expr()->eq('record.identifier', ':identifier'))
            ->setParameter('identifier', $identifier)
            ->setMaxResults(1);
        $query = $dql->getQuery();
        return (bool) $query->getOneOrNullResult(AbstractQuery::HYDRATE_SINGLE_SCALAR);
    }

    /**
     * Prune deleted records.
     *
     * @return int The number of removed records
     */
    public function pruneDeletedRecords(): int
    {
        $repository = $this->entityManager->getRepository(Record::class);
        $criteria = Criteria::create()->where(Criteria::expr()->isNull('content'));
        $records = $repository->matching($criteria);
        foreach ($records as $record) {
            $this->entityManager->remove($record);
        }
        $this->entityManager->flush();
        $this->pruneOrphanSets();
        return count($records);
    }

    /**
     * Prune orphan sets.
     *
     * @return void
     */
    public function pruneOrphanSets(): void
    {
        $repository = $this->entityManager->getRepository(Set::class);
        $sets = $repository->findAll();
        foreach ($sets as $set) {
            if ($set->isEmpty()) {
                $this->entityManager->remove($set);
            }
        }
        $this->entityManager->flush();
    }

    /**
     * Prune expired resumption tokens.
     *
     * @return int The number of deleted tokens
     */
    public function pruneResumptionTokens(): int
    {
        $repository = $this->entityManager->getRepository(Token::class);
        $criteria = Criteria::create()->where(Criteria::expr()->lt('validUntil', new DateTime()));
        $tokens = $repository->matching($criteria);
        foreach ($tokens as $token) {
            $this->entityManager->remove($token);
        }
        $this->entityManager->flush();
        return count($tokens);
    }

    /**
     * Remove metadata format and all associated records.
     *
     * @param string $prefix The metadata prefix
     *
     * @return bool TRUE on success or FALSE on failure
     */
    public function removeMetadataFormat(string $prefix): bool
    {
        $format = $this->entityManager->find(Format::class, $prefix);
        if (isset($format)) {
            $this->entityManager->remove($format);
            $this->entityManager->flush();
            $this->pruneOrphanSets();
            return true;
        } else {
            return false;
        }
    }

    /**
     * This is a singleton class, thus the constructor is private.
     *
     * Usage: Get an instance of this class by calling Database::getInstance()
     */
    private function __construct()
    {
        $configuration = new DoctrineConfiguration();
        $configuration->setAutoGenerateProxyClasses(
            ProxyFactory::AUTOGENERATE_NEVER
        );
        $configuration->setMetadataCache(
            new PhpFilesAdapter(
                'Metadata',
                0,
                __DIR__ . '/../var/cache'
            )
        );
        $configuration->setMetadataDriverImpl(
            new AttributeDriver([__DIR__ . '/Database'])
        );
        $configuration->setProxyDir(__DIR__ . '/../var/generated');
        $configuration->setProxyNamespace('OCC\OaiPmh2\Proxy');
        $configuration->setQueryCache(
            new PhpFilesAdapter(
                'Query',
                0,
                __DIR__ . '/../var/cache'
            )
        );
        $configuration->setResultCache(
            new PhpFilesAdapter(
                'Result',
                0,
                __DIR__ . '/../var/cache'
            )
        );
        $configuration->setSchemaAssetsFilter(
            static function(string|AbstractAsset $assetName): bool {
                if ($assetName instanceof AbstractAsset) {
                    $assetName = $assetName->getName();
                }
                return in_array($assetName, self::DB_TABLES, true);
            }
        );

        $baseDir = Path::canonicalize(__DIR__ . '/../');
        $dsn = str_replace('%BASEDIR%', $baseDir, Configuration::getInstance()->database);
        $parser = new DsnParser([
            'mariadb' => 'pdo_mysql',
            'mssql' => 'pdo_sqlsrv',
            'mysql' => 'pdo_mysql',
            'oracle' => 'pdo_oci',
            'postgres' => 'pdo_pgsql',
            'sqlite' => 'pdo_sqlite'
        ]);
        $connection = DriverManager::getConnection($parser->parse($dsn), $configuration);

        $this->entityManager = new EntityManager($connection, $configuration);
    }
}
