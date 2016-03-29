<?php

namespace go1\flood;

use Doctrine\DBAL\Connection;
use Vectorface\Whip\Whip;

class Flood
{
    private $connection;
    private $tableName = 'flood';
    private $whip;

    public function __construct(Connection $connection, $tableName, Whip $whip)
    {
        $this->connection = $connection;
        $this->tableName = $tableName;
        $this->whip = $whip;
    }

    private function ip()
    {
        return $this->whip->getIpAddress();
    }

    public function install()
    {
        $schema = $this->connection->getSchemaManager()->createSchema();
        $table = $schema->createTable($this->tableName);
        $table->addColumn('fid', 'integer', ['unsigned' => true, 'autoincrement' => true]);
        $table->addColumn('event', 'string', ['length' => 64]);
        $table->addColumn('identifier', 'string', ['length' => 128]);
        $table->addColumn('timestamp', 'integer', ['unsigned' => true]);
        $table->addColumn('expiration', 'integer', ['unsigned' => true]);
        $table->setPrimaryKey(['fid']);
        $table->addIndex(['event', 'identifier', 'timestamp'], 'index_allow');
        $table->addIndex(['expiration'], 'index_purge');
        foreach ($schema->toSql($this->connection->getDatabasePlatform()) as $sql) {
            $this->connection->executeQuery($sql);
        }
    }

    /**
     * Checks whether a user is allowed to proceed with the specified event.
     *
     * Events can have thresholds saying that each user can only do that event
     * a certain number of times in a time window. This function verifies that the
     * current user has not exceeded this threshold.
     *
     * @param string $name       The unique name of the event.
     * @param int    $threshold  The maximum number of times each user can do this event per time window.
     * @param int    $window     Number of seconds in the time window for this event (default is 3600 seconds/1 hour).
     * @param        $identifier Unique identifier of the current user. Defaults to their IP address.
     * @return bool
     */
    public function isAllowed($name, $threshold, $window = 3600, $identifier = null)
    {
        $identifier = $identifier ?: $this->ip();

        $sql = "SELECT COUNT(*) FROM {$this->tableName}";
        $sql .= " WHERE event = ? AND identifier = ? AND timestamp > ?";

        return $threshold > $this->connection
            ->executeQuery($sql, [$name, $identifier, time() - $window])
            ->fetchColumn();
    }

    /**
     * Registers an event for the current visitor to the flood control mechanism.
     *
     * @param string $name       The name of an event.
     * @param int    $window     Time to live in seconds.
     * @param int    $identifier Optional identifier (defaults to the current user's IP address).
     */
    public function register($name, $window = 3600, $identifier = null)
    {
        $this
            ->connection
            ->insert($this->tableName, [
                'event'      => $name,
                'identifier' => $identifier ?: $this->ip(),
                'timestamp'  => time(),
                'expiration' => time() + $window,
            ]);
    }

    public function clearEvent($name, $identifier = null)
    {
        $this->connection->executeQuery(
            "DELETE FROM {$this->tableName} WHERE event = ? AND identifier = ?",
            [$name, $identifier ?: $this->ip()]
        );
    }

    public function clear($time = null)
    {
        $this->connection->executeQuery(
            "DELETE FROM {$this->tableName} WHERE expiration <= ?",
            [$time ?: time()]
        );
    }
}
