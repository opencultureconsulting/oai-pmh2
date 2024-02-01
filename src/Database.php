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
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Configuration as DoctrineConfiguration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Proxy\ProxyFactory;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Tools\Pagination\Paginator;
use OCC\Basics\Traits\Singleton;
use OCC\OaiPmh2\Entity\Format;
use OCC\OaiPmh2\Entity\Record;
use OCC\OaiPmh2\Entity\Set;
use OCC\OaiPmh2\Entity\Token;
use OCC\OaiPmh2\Result;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Filesystem\Path;

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
     * @param Format $newFormat The metadata format
     *
     * @return void
     */
    public function addOrUpdateMetadataFormat(Format $newFormat): void
    {
        $oldFormat = $this->entityManager->find(Format::class, $newFormat->getPrefix());
        if (isset($oldFormat)) {
            $oldFormat->setNamespace($newFormat->getNamespace());
            $oldFormat->setSchema($newFormat->getSchema());
        } else {
            $this->entityManager->persist($newFormat);
        }
        $this->entityManager->flush();
    }

    /**
     * Add or update record.
     *
     * @param Record $newRecord The record
     * @param bool $bulkMode Should we operate in bulk mode (no flush)?
     *
     * @return void
     */
    public function addOrUpdateRecord(Record $newRecord, bool $bulkMode = false): void
    {
        $oldRecord = $this->entityManager->find(
            Record::class,
            [
                'identifier' => $newRecord->getIdentifier(),
                'format' => $newRecord->getFormat()
            ]
        );
        if (isset($oldRecord)) {
            if ($newRecord->hasContent() || Configuration::getInstance()->deletedRecords !== 'no') {
                $oldRecord->setContent($newRecord->getContent(), false);
                $oldRecord->setLastChanged($newRecord->getLastChanged());
                // Add new sets.
                foreach (array_diff($newRecord->getSets(), $oldRecord->getSets()) as $newSet) {
                    $oldRecord->addSet($newSet);
                }
                // Remove old sets.
                foreach (array_diff($oldRecord->getSets(), $newRecord->getSets()) as $oldSet) {
                    $oldRecord->removeSet($oldSet);
                }
            } else {
                $this->entityManager->remove($oldRecord);
            }
        } else {
            if ($newRecord->hasContent() || Configuration::getInstance()->deletedRecords !== 'no') {
                $this->entityManager->persist($newRecord);
            }
        }
        if (!$bulkMode) {
            $this->entityManager->flush();
        }
    }

    /**
     * Add or update set.
     *
     * @param Set $newSet The set
     *
     * @return void
     */
    public function addOrUpdateSet(Set $newSet): void
    {
        $oldSet = $this->entityManager->find(Set::class, $newSet->getSpec());
        if (isset($oldSet)) {
            $oldSet->setName($newSet->getName());
            $oldSet->setDescription($newSet->getDescription());
        } else {
            $this->entityManager->persist($newSet);
        }
        $this->entityManager->flush();
    }

    /**
     * Delete metadata format and all associated records.
     *
     * @param Format $format The metadata format
     *
     * @return void
     */
    public function deleteMetadataFormat(Format $format): void
    {
        $dql = $this->entityManager->createQueryBuilder();
        $dql->delete(Record::class, 'record')
            ->where($dql->expr()->eq('record.format', ':format'))
            ->setParameter('format', $format->getPrefix());
        $query = $dql->getQuery();
        $query->execute();

        // Explicitly remove associations with sets for deleted records.
        $sql = $this->entityManager->getConnection();
        $sql->executeStatement("DELETE FROM records_sets WHERE record_format='{$format->getPrefix()}'");

        $this->entityManager->remove($format);
        $this->entityManager->flush();

        $this->pruneOrphanSets();
    }

    /**
     * Delete a record.
     *
     * @param Record $record The record
     *
     * @return void
     */
    public function deleteRecord(Record $record): void
    {
        if (Configuration::getInstance()->deletedRecords === 'no') {
            $this->entityManager->remove($record);
        } else {
            $record->setContent(null);
            $record->setLastChanged(new DateTime());
        }
        $this->entityManager->flush();
        $this->pruneOrphanSets();
    }

    /**
     * Flush all changes to the database.
     *
     * @param string[] $entities Optional array of entity types to clear from entity manager
     *
     * @return void
     */
    public function flush(array $entities = []): void
    {
        $this->entityManager->flush();
        foreach ($entities as $entity) {
            $this->entityManager->clear($entity);
        }
    }

    /**
     * Get all sets without pagination.
     *
     * @return Result<Sets> The sets
     */
    public function getAllSets(): Result
    {
        $dql = $this->entityManager->createQueryBuilder();
        $dql->select('sets')
            ->from(Set::class, 'sets', 'sets.spec');
        $query = $dql->getQuery();
        $query->enableResultCache();
        /** @var Sets $resultQuery */
        $resultQuery = $query->getResult();
        return new Result($resultQuery);
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
        $dql->select($dql->expr()->min('record.lastChanged'))
            ->from(Record::class, 'record');
        $query = $dql->getQuery();
        $query->enableResultCache();
        /** @var ?string $result */
        $result = $query->getOneOrNullResult(AbstractQuery::HYDRATE_SCALAR_COLUMN);
        return $result ?? $timestamp;
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
            $dql->innerJoin(Record::class, 'record')
                ->where(
                    $dql->expr()->andX(
                        $dql->expr()->eq('record.identifier', ':identifier'),
                        $dql->expr()->isNotNull('record.content')
                    )
                )
                ->setParameter('identifier', $identifier);
        }
        $query = $dql->getQuery();
        $query->enableResultCache();
        /** @var Formats $queryResult */
        $queryResult = $query->getResult();
        return new Result($queryResult);
    }

    /**
     * Get a single record.
     *
     * @param string $identifier The record identifier
     * @param Format $format The metadata format
     *
     * @return ?Record The record or NULL on failure
     */
    public function getRecord(string $identifier, Format $format): ?Record
    {
        return $this->entityManager->find(
            Record::class,
            [
                'identifier' => $identifier,
                'format' => $format
            ]
        );
    }

    /**
     * Get list of records.
     *
     * @param string $verb The currently requested verb ('ListIdentifiers' or 'ListRecords')
     * @param Format $metadataPrefix The metadata format
     * @param int $counter Counter for split result sets
     * @param ?DateTime $from The "from" datestamp
     * @param ?DateTime $until The "until" datestamp
     * @param ?Set $set The set spec
     *
     * @return Result<Records> The records and possibly a resumtion token
     */
    public function getRecords(
        string $verb,
        Format $metadataPrefix,
        int $counter = 0,
        ?DateTime $from = null,
        ?DateTime $until = null,
        ?Set $set = null
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
            $dql->setParameter('from', $from);
            $from = $from->format('Y-m-d\TH:i:s\Z');
        }
        if (isset($until)) {
            $dql->andWhere($dql->expr()->lte('record.lastChanged', ':until'));
            $dql->setParameter('until', $until);
            $until = $until->format('Y-m-d\TH:i:s\Z');
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
            $dql->setParameter('setSpec', $set->getSpec());
            $dql->setParameter('setLike', $set->getSpec() . ':%');
            $set = $set->getSpec();
        }
        $query = $dql->getQuery();
        /** @var Records $queryResult */
        $queryResult = $query->getResult();
        $result = new Result($queryResult);
        $paginator = new Paginator($query, true);
        if (count($paginator) > ($cursor + count($result))) {
            $token = new Token($verb, [
                'counter' => $counter + 1,
                'completeListSize' => count($paginator),
                'metadataPrefix' => $metadataPrefix->getPrefix(),
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
    public function getSets(int $counter = 0): Result
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
        /** @var Sets $queryResult */
        $queryResult = $query->getResult();
        $result = new Result($queryResult);
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
        $dql->select($dql->expr()->count('record.identifier'))
            ->from(Record::class, 'record')
            ->where($dql->expr()->eq('record.identifier', ':identifier'))
            ->setParameter('identifier', $identifier);
        $query = $dql->getQuery();
        return (bool) $query->getOneOrNullResult(AbstractQuery::HYDRATE_SCALAR_COLUMN);
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
     * @return int The number of removed sets
     */
    public function pruneOrphanSets(): int
    {
        $repository = $this->entityManager->getRepository(Set::class);
        $sets = $repository->findAll();
        $count = 0;
        foreach ($sets as $set) {
            if ($set->isEmpty()) {
                $this->entityManager->remove($set);
                ++$count;
            }
        }
        $this->entityManager->flush();
        return $count;
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
            new AttributeDriver([__DIR__ . '/Entity'])
        );
        $configuration->setProxyDir(__DIR__ . '/../var/generated');
        $configuration->setProxyNamespace('OCC\OaiPmh2\Entity\Proxy');
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
            'postgresql' => 'pdo_pgsql',
            'sqlite' => 'pdo_sqlite'
        ]);
        $connection = DriverManager::getConnection($parser->parse($dsn), $configuration);

        $this->entityManager = new EntityManager($connection, $configuration);
    }
}
