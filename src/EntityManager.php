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
use Doctrine\DBAL\ParameterType;
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
use OCC\OaiPmh2\Driver\Middleware as DriverMiddleware;
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
 * @phpstan-import-type Params from DriverManager
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
final class EntityManager extends EntityManagerDecorator
{
    use Singleton;

    /**
     * The database tables this class is allowed to handle.
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
        $this->getRepository(get_class($entity))->addOrUpdate($entity);
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
        $this->getRepository(get_class($entity))->delete($entity);
    }

    /**
     * Get the earliest datestamp of any record.
     *
     * @return string The earliest datestamp
     */
    public function getEarliestDatestamp(): string
    {
        $timestamp = '0000-01-01T00:00:00Z';
        $dql = $this->createQueryBuilder();
        $dql->select($dql->expr()->min('records.lastChanged'));
        $dql->from(Record::class, 'records');
        $query = $dql->getQuery()->enableResultCache();
        /** @var ?DateTime */
        $result = $query->getOneOrNullResult(AbstractQuery::HYDRATE_SCALAR_COLUMN);
        return $result?->format('Y-m-d\TH:i:s\Z') ?? $timestamp;
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
        return $this->getReference(Format::class, $prefix);
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
            $formats = $this->getRepository(Format::class)->findAll();
        } else {
            $dql = $this->createQueryBuilder();
            $dql->select('formats')
                ->from(Format::class, 'formats')
                ->innerJoin('formats.records', 'records')
                ->where($dql->expr()->eq('records.identifier', ':recordIdentifier'))
                ->setParameter('recordIdentifier', $recordIdentifier);
            $query = $dql->getQuery()->enableResultCache();
            /** @var Format[] */
            $formats = $query->getResult(AbstractQuery::HYDRATE_OBJECT);
        }
        foreach ($formats as $format) {
            $entities[$format->getPrefix()] = $format;
        }
        return new ResultSet($entities);
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
        return $this->getRepository(Record::class)->findOneBy([
            'identifier' => $identifier,
            'metadataPrefix' => $this->getMetadataFormat($format)
        ]);
    }

    /**
     * Get list of records.
     *
     * @param 'ListIdentifiers'|'ListRecords' $verb The currently requested verb
     * @param non-empty-string $metadataPrefix The metadata prefix
     * @param non-negative-int $counter Counter for split result sets
     * @param ?non-empty-string $from The "from" datestamp
     * @param ?non-empty-string $until The "until" datestamp
     * @param ?non-empty-string $set The set spec
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
        $dql->select('records')
            ->from(Record::class, 'records', 'records.identifier')
            ->where($dql->expr()->eq('records.metadataPrefix', ':metadataPrefix'))
            ->setParameter('metadataPrefix', $this->getMetadataFormat($metadataPrefix))
            ->setFirstResult($cursor)
            ->setMaxResults($maxRecords);
        if (isset($from)) {
            $dql->andWhere($dql->expr()->gte('records.lastChanged', ':from'));
            $dql->setParameter('from', new DateTime($from), 'datetime');
        }
        if (isset($until)) {
            $dql->andWhere($dql->expr()->lte('records.lastChanged', ':until'));
            $dql->setParameter('until', new DateTime($until), 'datetime');
        }
        if (isset($set)) {
            $dql->innerJoin(
                Set::class,
                'sets',
                Join::WITH,
                $dql->expr()->orX(
                    $dql->expr()->eq('sets.spec', ':setSpec'),
                    $dql->expr()->like('sets.spec', ':setLike')
                )
            );
            $dql->setParameter('setSpec', $set);
            $dql->setParameter('setLike', $set . ':%');
        }
        $query = $dql->getQuery();
        /** @var array<non-empty-string, Record> */
        $queryResult = $query->getResult();
        $result = new ResultSet($queryResult);
        $paginator = new Paginator($query, true);
        if (count($paginator) > ($cursor + count($result))) {
            /** @psalm-suppress ArgumentTypeCoercion */
            $token = new Token(
                $verb,
                array_filter([
                    'verb' => $verb,
                    'metadataPrefix' => $metadataPrefix,
                    'from' => $from,
                    'until' => $until,
                    'set' => $set,
                    'counter' => $counter + 1,
                    'completeListSize' => count($paginator)
                ], fn ($value): bool => isset($value))
            );
            $this->persist($token);
            $this->flush();
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
        $resumptionToken = $this->getRepository(Token::class)->findOneBy([
            'token' => $token,
            'verb' => $verb
        ]);
        if (isset($resumptionToken) && $resumptionToken->getValidUntil() < new DateTime()) {
            $this->delete($resumptionToken);
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
        return $this->getReference(Set::class, $spec);
    }

    /**
     * Get all available sets.
     *
     * @param non-negative-int $counter Counter for split result sets
     *
     * @return ResultSet<Set> The sets indexed by spec
     */
    public function getSets(int $counter = 0): ResultSet
    {
        $maxRecords = Configuration::getInstance()->maxRecords;
        $cursor = $counter * $maxRecords;

        $dql = $this->createQueryBuilder();
        $dql->select('sets')
            ->from(Set::class, 'sets', 'sets.spec')
            ->setFirstResult($cursor)
            ->setMaxResults($maxRecords);
        $query = $dql->getQuery()->enableResultCache();
        /** @var array<non-empty-string, Set> */
        $queryResult = $query->getResult(AbstractQuery::HYDRATE_OBJECT);
        $result = new ResultSet($queryResult);
        $paginator = new Paginator($query);
        if (count($paginator) > ($cursor + count($result))) {
            $token = new Token(
                'ListSets',
                [
                    'verb' => 'ListSets',
                    'counter' => $counter + 1,
                    'completeListSize' => count($paginator)
                ]
            );
            $this->persist($token);
            $this->flush();
            $result->setResumptionToken($token);
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
        $records = $this->getRepository(Record::class)->findBy(['identifier' => $identifier]);
        return count($records) > 0;
    }

    /**
     * Prune deleted records.
     *
     * @return int The number of removed records
     */
    public function pruneDeletedRecords(): int
    {
        $dql = $this->createQueryBuilder();
        $dql->delete(Record::class, 'records')
            ->where($dql->expr()->isNull('records.content'));
        /** @var non-negative-int */
        return $dql->getQuery()->execute();
    }

    /**
     * Prune expired resumption tokens.
     *
     * @return int The number of deleted tokens
     */
    public function pruneExpiredTokens(): int
    {
        $dql = $this->createQueryBuilder();
        $dql->delete(Token::class, 'tokens')
            ->where($dql->expr()->lt('tokens.validUntil', ':now'))
            ->setParameter('now', new DateTime(), 'datetime');
        /** @var non-negative-int */
        return $dql->getQuery()->execute();
    }

    /**
     * Prune orphaned sets.
     *
     * @return int The number of removed sets
     */
    public function pruneOrphanedSets(): int
    {
        $sets = $this->getRepository(Set::class)->findAll();
        $count = 0;
        foreach ($sets as $set) {
            if ($set->isEmpty()) {
                $count += 1;
                $this->remove($set);
            }
        }
        if ($count > 0) {
            $this->flush();
        }
        return $count;
    }

    /**
     * Purge all records with given metadata prefix.
     *
     * @param string $metadataPrefix The metadata prefix
     *
     * @return int The number of affected records
     */
    public function purgeRecords(string $metadataPrefix): int
    {
        $dql = $this->createQueryBuilder();
        if (Configuration::getInstance()->deletedRecords === 'no') {
            $dql->delete(Record::class, 'records');
        } else {
            $dql->update(Record::class, 'records')
                ->set('records.content', ':null')
                ->set('records.lastChanged', ':now')
                ->setParameter('null', null, ParameterType::NULL)
                ->setParameter('now', new DateTime(), 'datetime');
        }
        $dql->where($dql->expr()->eq('records.metadataPrefix', ':metadataPrefix'))
            ->setParameter('metadataPrefix', $this->getMetadataFormat($metadataPrefix));
        /** @var non-negative-int */
        return $dql->getQuery()->execute();
    }

    /**
     * Instantiate new Doctrine entity manager and connect to database.
     */
    private function __construct()
    {
        $config = new DoctrineConfiguration();
        if (PHP_VERSION_ID < 80400) {
            $config->setAutoGenerateProxyClasses(ProxyFactory::AUTOGENERATE_NEVER);
            $config->setProxyDir(__DIR__ . '/../var/generated');
            $config->setProxyNamespace('OCC\OaiPmh2\Entity\Proxy');
        } else {
            $config->enableNativeLazyObjects(true);
        }
        $config->setMetadataCache(new PhpFilesAdapter('Metadata', 0, __DIR__ . '/../var/cache'));
        $config->setMetadataDriverImpl(new AttributeDriver([__DIR__ . '/Entity']));
        $config->setMiddlewares([new DriverMiddleware()]);
        $config->setQueryCache(new PhpFilesAdapter('Query', 0, __DIR__ . '/../var/cache'));
        $config->setResultCache(new PhpFilesAdapter('Result', 0, __DIR__ . '/../var/cache'));
        $config->setSchemaAssetsFilter(
            static function (string|AbstractAsset $assetName): bool {
                if ($assetName instanceof AbstractAsset) {
                    $assetName = $assetName->getName();
                }
                return in_array($assetName, self::TABLES, true);
            }
        );

        $dsn = str_replace(
            '%BASEDIR%',
            Path::canonicalize(__DIR__ . '/../'),
            Configuration::getInstance()->database
        );
        $parser = new DsnParser([
            'mariadb' => 'pdo_mysql',
            'mssql' => 'pdo_sqlsrv',
            'mysql' => 'pdo_mysql',
            'oracle' => 'pdo_oci',
            'postgresql' => 'pdo_pgsql',
            'sqlite' => 'pdo_sqlite'
        ]);
        $conn = DriverManager::getConnection(
            $parser->parse($dsn),
            $config
        );

        parent::__construct(new DoctrineEntityManager($conn, $config));
    }
}
