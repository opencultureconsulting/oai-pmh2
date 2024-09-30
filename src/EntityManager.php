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

use DateTime;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Configuration as DoctrineConfiguration;
use Doctrine\ORM\Decorator\EntityManagerDecorator;
use Doctrine\ORM\EntityManager as DoctrineEntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Proxy\ProxyFactory;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Tools\Pagination\Paginator;
use OCC\Basics\Traits\Singleton;
use OCC\OaiPmh2\Entity\Format;
use OCC\OaiPmh2\Entity\Record;
use OCC\OaiPmh2\Entity\Set;
use OCC\OaiPmh2\Entity\Token;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Filesystem\Path;

/**
 * The Entity Manager controls all database shenanigans.
 *
 * @author Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 * @package OAIPMH2
 *
 * @mixin DoctrineEntityManager
 *
 * @psalm-import-type Params from DriverManager
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
final class EntityManager extends EntityManagerDecorator
{
    use Singleton;

    /**
     * The database tables this class is allowed to handle.
     *
     * @var string[]
     */
    private const TABLES = [
        'formats',
        'records',
        'records_sets',
        'sets',
        'tokens'
    ];

    /**
     * Add or update entity.
     *
     * @param Format|Record|Set|Token $entity The entity
     * @param bool $bulkMode Should we operate in bulk mode (no flush)?
     *
     * @return void
     */
    public function addOrUpdate(Format|Record|Set|Token $entity, bool $bulkMode = false): void
    {
        $this->getRepository(className: get_class($entity))->addOrUpdate(entity: $entity);
        if (!$bulkMode) {
            $this->flush();
        }
    }

    /**
     * Delete entity.
     *
     * @param Format|Record|Set|Token $entity The entity
     *
     * @return void
     */
    public function delete(Format|Record|Set|Token $entity): void
    {
        $this->getRepository(className: get_class($entity))->delete(entity: $entity);
    }

    /**
     * Get the earliest datestamp of any record.
     *
     * @return string The earliest datestamp
     */
    public function getEarliestDatestamp(): string
    {
        $timestamp = '0000-00-00T00:00:00Z';
        $dql = $this->createQueryBuilder();
        $dql->select(select: $dql->expr()->min('record.lastChanged'));
        $dql->from(from: Record::class, alias: 'record');
        $query = $dql->getQuery()->enableResultCache();
        /** @var ?string $result */
        $result = $query->getOneOrNullResult(hydrationMode: AbstractQuery::HYDRATE_SCALAR_COLUMN);
        return $result ?? $timestamp;
    }

    /**
     * Get reference to a single metadata format.
     *
     * @param string $prefix The metadata prefix
     *
     * @return ?Format The reference to the metadata format or NULL if invalid
     */
    public function getMetadataFormat(string $prefix): ?Format
    {
        return $this->getReference(entityName: Format::class, id: $prefix);
    }

    /**
     * Get all available metadata formats (optionally for a given record identifier).
     *
     * @param ?string $recordIdentifier Optional record identifier
     *
     * @return ResultSet<Format> The metadata formats indexed by prefix
     */
    public function getMetadataFormats(?string $recordIdentifier = null): ResultSet
    {
        $entities = [];
        if ($recordIdentifier === null) {
            $formats = $this->getRepository(className: Format::class)->findAll();
        } else {
            $dql = $this->createQueryBuilder();
            $dql->select(select: 'record.format')
                ->from(from: Record::class, alias: 'record')
                ->where(predicates: $dql->expr()->eq('record.identifier', ':recordIdentifier'))
                ->setParameter(key: 'recordIdentifier', value: $recordIdentifier);
            $query = $dql->getQuery()->enableResultCache();
            /** @var Format[] */
            $formats = $query->getResult(hydrationMode: AbstractQuery::HYDRATE_OBJECT);
        }
        foreach ($formats as $format) {
            $entities[$format->getPrefix()] = $format;
        }
        return new ResultSet(elements: $entities);
    }

    /**
     * Get a single record.
     *
     * @param string $identifier The record identifier
     * @param string $format The metadata prefix
     *
     * @return ?Record The record or NULL if invalid
     */
    public function getRecord(string $identifier, string $format): ?Record
    {
        return $this->getRepository(className: Record::class)->findOneBy(
            criteria: [
                'identifier' => $identifier,
                'format' => $this->getMetadataFormat(prefix: $format)
            ]
        );
    }

    /**
     * Get list of records.
     *
     * @param string $verb The currently requested verb
     *                     'ListIdentifiers' or 'ListRecords'
     * @param string $metadataPrefix The metadata prefix
     * @param int $counter Counter for split result sets
     * @param ?string $from The "from" datestamp
     * @param ?string $until The "until" datestamp
     * @param ?string $set The set spec
     *
     * @return ResultSet<Record> The records indexed by id and maybe a resumption token
     */
    public function getRecords(
        string $verb,
        string $metadataPrefix,
        int $counter = 0,
        ?string $from = null,
        ?string $until = null,
        ?string $set = null
    ): ResultSet {
        $maxRecords = Configuration::getInstance()->maxRecords;
        $cursor = $counter * $maxRecords;

        $dql = $this->createQueryBuilder();
        $dql->select(select: 'record')
            ->from(from: Record::class, alias: 'record', indexBy: 'record.identifier')
            ->where(predicates: $dql->expr()->eq('record.format', ':metadataPrefix'))
            ->setParameter(
                key: 'metadataPrefix',
                value: $this->getMetadataFormat(prefix: $metadataPrefix)
            )
            ->setFirstResult(firstResult: $cursor)
            ->setMaxResults(maxResults: $maxRecords);
        if (isset($from)) {
            $dql->andWhere(where: $dql->expr()->gte('record.lastChanged', ':from'));
            $dql->setParameter(key: 'from', value: new DateTime($from));
        }
        if (isset($until)) {
            $dql->andWhere(where: $dql->expr()->lte('record.lastChanged', ':until'));
            $dql->setParameter(key: 'until', value: new DateTime($until));
        }
        if (isset($set)) {
            $dql->innerJoin(
                join: Set::class,
                alias: 'sets',
                conditionType: Join::WITH,
                condition: $dql->expr()->orX(
                    $dql->expr()->eq('sets.spec', ':setSpec'),
                    $dql->expr()->like('sets.spec', ':setLike')
                )
            );
            $dql->setParameter(key: 'setSpec', value: $set);
            $dql->setParameter(key: 'setLike', value: $set . ':%');
        }
        $query = $dql->getQuery();
        /** @var array<string, Record> */
        $queryResult = $query->getResult();
        $result = new ResultSet(elements: $queryResult);
        $paginator = new Paginator(query: $query, fetchJoinCollection: true);
        if (count($paginator) > ($cursor + count($result))) {
            $token = new Token(
                verb: $verb,
                parameters: [
                    'verb' => $verb,
                    'identifier' => null,
                    'metadataPrefix' => $metadataPrefix,
                    'from' => $from,
                    'until' => $until,
                    'set' => $set,
                    'resumptionToken' => null,
                    'counter' => $counter + 1,
                    'completeListSize' => count($paginator)
                ]
            );
            $this->persist(object: $token);
            $this->flush();
            $result->setResumptionToken(token: $token);
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
        $resumptionToken = $this->getRepository(className: Token::class)->findOneBy(
            criteria: [
                'token' => $token,
                'verb' => $verb
            ]
        );
        if (isset($resumptionToken) && $resumptionToken->getValidUntil() < new DateTime()) {
            $this->delete(entity: $resumptionToken);
            return null;
        }
        return $resumptionToken;
    }

    /**
     * Get reference to a single set.
     *
     * @param string $spec The set spec
     *
     * @return ?Set The reference to the set or NULL if invalid
     */
    public function getSet(string $spec): ?Set
    {
        return $this->getReference(entityName: Set::class, id: $spec);
    }

    /**
     * Get all available sets.
     *
     * @param int $counter Counter for split result sets
     *
     * @return ResultSet<Set> The sets indexed by spec
     */
    public function getSets(int $counter = 0): ResultSet
    {
        $maxRecords = Configuration::getInstance()->maxRecords;
        $cursor = $counter * $maxRecords;

        $dql = $this->createQueryBuilder();
        $dql->select(select: 'set')
            ->from(from: Set::class, alias: 'set', indexBy: 'set.spec')
            ->setFirstResult(firstResult: $cursor)
            ->setMaxResults(maxResults: $maxRecords);
        $query = $dql->getQuery()->enableResultCache();
        /** @var array<string, Set> */
        $queryResult = $query->getResult(hydrationMode: AbstractQuery::HYDRATE_OBJECT);
        $result = new ResultSet(elements: $queryResult);
        $paginator = new Paginator(query: $query);
        if (count($paginator) > ($cursor + count($result))) {
            $token = new Token(
                verb: 'ListSets',
                parameters: [
                    'verb' => 'ListSets',
                    'identifier' => null,
                    'metadataPrefix' => null,
                    'from' => null,
                    'until' => null,
                    'set' => null,
                    'resumptionToken' => null,
                    'counter' => $counter + 1,
                    'completeListSize' => count($paginator)
                ]
            );
            $this->persist(object: $token);
            $this->flush();
            $result->setResumptionToken(token: $token);
        }
        return $result;
    }

    /**
     * Check if a record with the given identifier exists.
     *
     * @param string $identifier The record identifier
     *
     * @return bool Whether a record with the identifier exists
     */
    public function isValidRecordIdentifier(string $identifier): bool
    {
        $records = $this->getRepository(className: Record::class)->findBy(criteria: ['identifier' => $identifier]);
        return (bool) count($records) > 0;
    }

    /**
     * Prune deleted records.
     *
     * @return int The number of removed records
     */
    public function pruneDeletedRecords(): int
    {
        $dql = $this->createQueryBuilder();
        $dql->delete(delete: Record::class, alias: 'record')
            ->where(predicates: $dql->expr()->isNull('record.content'));
        /** @var int */
        $deleted = $dql->getQuery()->execute();
        if ($deleted > 0) {
            $this->pruneOrphanedSets();
        }
        return $deleted;
    }

    /**
     * Prune expired resumption tokens.
     *
     * @return int The number of deleted tokens
     */
    public function pruneExpiredTokens(): int
    {
        $dql = $this->createQueryBuilder();
        $dql->delete(delete: Token::class, alias: 'token')
            ->where(predicates: $dql->expr()->lt('token.validUntil', new DateTime()));
        /** @var int */
        return $dql->getQuery()->execute();
    }

    /**
     * Prune orphan sets.
     *
     * @return int The number of removed sets
     */
    public function pruneOrphanedSets(): int
    {
        $sets = $this->getRepository(className: Set::class)->findAll();
        $count = 0;
        foreach ($sets as $set) {
            if ($set->isEmpty()) {
                $count += 1;
                $this->remove(object: $set);
            }
        }
        if ($count > 0) {
            $this->flush();
        }
        return $count;
    }

    /**
     * Instantiate new Doctrine entity manager and connect to database.
     */
    private function __construct()
    {
        $config = new DoctrineConfiguration();
        $config->setAutoGenerateProxyClasses(
            autoGenerate: ProxyFactory::AUTOGENERATE_NEVER
        );
        $config->setMetadataCache(
            cache: new PhpFilesAdapter(
                namespace: 'Metadata',
                directory: __DIR__ . '/../var/cache'
            )
        );
        $config->setMetadataDriverImpl(
            driverImpl: new AttributeDriver(
                paths: [__DIR__ . '/Entity']
            )
        );
        $config->setProxyDir(dir: __DIR__ . '/../var/generated');
        $config->setProxyNamespace(ns: 'OCC\OaiPmh2\Entity\Proxy');
        $config->setQueryCache(
            cache: new PhpFilesAdapter(
                namespace: 'Query',
                directory: __DIR__ . '/../var/cache'
            )
        );
        $config->setResultCache(
            cache: new PhpFilesAdapter(
                namespace: 'Result',
                directory: __DIR__ . '/../var/cache'
            )
        );
        $config->setSchemaAssetsFilter(
            schemaAssetsFilter: static function (string|AbstractAsset $assetName): bool {
                if ($assetName instanceof AbstractAsset) {
                    $assetName = $assetName->getName();
                }
                return in_array(needle: $assetName, haystack: self::TABLES, strict: true);
            }
        );

        $baseDir = Path::canonicalize(path: __DIR__ . '/../');
        $dsn = str_replace(
            search: '%BASEDIR%',
            replace: $baseDir,
            subject: Configuration::getInstance()->database
        );
        $parser = new DsnParser(
            schemeMapping: [
                'mariadb' => 'pdo_mysql',
                'mssql' => 'pdo_sqlsrv',
                'mysql' => 'pdo_mysql',
                'oracle' => 'pdo_oci',
                'postgresql' => 'pdo_pgsql',
                'sqlite' => 'pdo_sqlite'
            ]
        );
        $conn = DriverManager::getConnection(
            // Generic return type of DsnParser::parse() is not correctly recognized.
            // phpcs:ignore
            params: $parser->parse(dsn: $dsn),
            config: $config
        );

        parent::__construct(new DoctrineEntityManager(conn: $conn, config: $config));
    }
}
