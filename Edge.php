<?php

namespace go1\edge;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\Schema;
use PDO;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Edge
{
    private $connection;
    private $tableName = 'edge';
    private $defaultType;
    private $dispatcher;

    const EVENT_LINK_DUPLICATE = 'edge.link.duplicate';

    public function __construct(
        Connection $connection,
        $tableName = 'edge',
        $defaultType = null,
        EventDispatcherInterface $dispatcher = null
    )
    {
        $this->connection = $connection;
        $this->tableName = $tableName;
        $this->defaultType = $defaultType;
        $this->dispatcher = $dispatcher;
    }

    public function install($execute = true)
    {
        static::migrate(
            $schema = $this->connection->getSchemaManager()->createSchema(),
            $this->tableName
        );

        if ($execute) {
            foreach ($schema->toSql($this->connection->getDatabasePlatform()) as $sql) {
                $this->connection->executeQuery($sql);
            }
        }

        return $schema;
    }

    public static function migrate(Schema $schema, $tableName)
    {
        $table = $schema->createTable($tableName);
        $table->addColumn('id', 'integer', ['unsigned' => true, 'autoincrement' => true]);
        $table->addColumn('type', 'integer');
        $table->addColumn('source_id', 'integer');
        $table->addColumn('target_id', 'integer');
        $table->addColumn('weight', 'integer');
        $table->addColumn('timestamp', 'integer', ['unsigned' => true]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['type', 'source_id'], 'index_source');
        $table->addIndex(['type', 'target_id'], 'index_target');
        $table->addIndex(['weight'], 'index_weight');
        $table->addIndex(['timestamp'], 'index_timestamp');
        $table->addUniqueIndex(['type', 'source_id', 'target_id'], 'unique_relationship');
    }

    private function claimType($type)
    {
        $type = (null !== $type) ? $type : $this->defaultType;

        if (null === $type) {
            throw new \RuntimeException('Invalid edge type.');
        }

        return $type;
    }

    public function link($sourceId, $targetId, $weight = 0, $type = null)
    {
        $type = $this->claimType($type);
        $key = ['type' => $type, 'source_id' => $sourceId, 'target_id' => $targetId];
        $ro = $key + ['weight' => $weight, 'timestamp' => time()];

        try {
            $this->connection->insert($this->tableName, $ro);

            return $this->connection->lastInsertId($this->tableName);
        }
        catch (UniqueConstraintViolationException $e) {
            // Reload current one, override weight & timestamp.
            $arguments = ['timestamp' => time()] + $this
                    ->connection
                    ->executeQuery(
                        "SELECT * FROM {$this->tableName} WHERE type = ? AND source_id = ? AND target_id = ?",
                        [$type, $sourceId, $targetId]
                    )
                    ->fetch();

            // Allow hacking weight by sending non-numeric value.
            if (is_numeric($weight)) {
                $arguments['weight'] = $weight;
            }

            $id = $arguments['id'];
            $this->dispatcher->dispatch(static::EVENT_LINK_DUPLICATE, $event = new EdgeEvent($this, $arguments));
            $this->connection->update($this->tableName, $event->getArguments(), ['id' => $id]);

            return $id;
        }
    }

    public function getTargetIdsIterator($sourceIds, $type = null)
    {
        $type = $this->claimType($type);

        return $this
            ->connection
            ->executeQuery(
                "SELECT target_id, source_id FROM {$this->tableName} WHERE type = ? AND source_id IN (?) ORDER BY weight ASC",
                [$type, is_array($sourceIds) ? $sourceIds : [$sourceIds]],
                [PDO::PARAM_INT, Connection::PARAM_INT_ARRAY]
            );
    }

    public function getTargetIds($sourceIds, $type = null)
    {
        $type = $this->claimType($type);
        $scalar = is_scalar($sourceIds);
        $state = $this->getTargetIdsIterator($sourceIds, $type);

        if ($scalar) {
            return $state->fetchAll(PDO::FETCH_COLUMN);
        }

        $ro = [];
        while ($row = $state->fetch()) {
            $ro[$row['source_id']][] = $row['target_id'];
        }

        return $ro;
    }

    public function getSourceIdsIterator($targetIds, $type = null)
    {
        $type = $this->claimType($type);

        return $this
            ->connection
            ->executeQuery(
                "SELECT source_id, target_id FROM {$this->tableName} WHERE type = ? AND target_id IN (?)",
                [$type, is_array($targetIds) ? $targetIds : [$targetIds]],
                [PDO::PARAM_INT, Connection::PARAM_INT_ARRAY]
            );
    }

    public function getSourceIds($targetIds, $type = null)
    {
        $type = $this->claimType($type);
        $scalar = is_scalar($targetIds);
        $query = $this->getSourceIdsIterator($targetIds, $type);

        if ($scalar) {
            return $query->fetchAll(PDO::FETCH_COLUMN);
        }

        $ro = [];
        while ($row = $query->fetch()) {
            $ro[$row['target_id']][] = $row['source_id'];
        }

        return $ro;
    }

    public function clearUsingSource($sourceIds, $type = null)
    {
        $type = $this->claimType($type);

        return $this
            ->connection
            ->executeQuery(
                "DELETE FROM {$this->tableName} WHERE type = ? AND source_id IN (?)",
                [$type, is_array($sourceIds) ? $sourceIds : [$sourceIds]],
                [PDO::PARAM_INT, Connection::PARAM_INT_ARRAY]
            );
    }

    public function clearUsingTarget($targetIds, $type = null)
    {
        $type = $this->claimType($type);

        return $this
            ->connection
            ->executeQuery(
                "DELETE FROM {$this->tableName} WHERE type = ? AND target_id IN (?)",
                [$type, is_array($targetIds) ? $targetIds : [$targetIds]],
                [PDO::PARAM_INT, Connection::PARAM_INT_ARRAY]
            );
    }
}
