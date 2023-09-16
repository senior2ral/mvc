<?php
namespace Mvc\Db;

use Mvc\Db\ModelManagerInterface;
use Mvc\Exception;
use ReflectionClass;

abstract class ModelManager implements ModelManagerInterface
{
    protected static $_server;
    protected static $_db;
    protected static $_source;
    protected static $_connection;

    /**
     * _id
     *
     * @var \MongoDB\BSON\ObjectID
     */
    public $_id;

    /**
     * find
     *
     * @param  array|object $parameters
     * @return array<static>
     */
    public static function find($parameters = [])
    {
        self::execute();

        $options = [];
        if (isset($parameters['sort'])) {
            $options['sort'] = $parameters['sort'];
        }

        if (isset($parameters['limit'])) {
            $options['limit'] = $parameters['limit'];
        }

        if (isset($parameters['skip'])) {
            $options['skip'] = $parameters['skip'];
        }

        if (isset($parameters['projection'])) {
            $options['projection'] = \array_fill_keys($parameters['projection'], true);
        }

        $filter = [];
        if (isset($parameters[0]) && \is_array($parameters[0])) {
            $filter = $parameters[0];
        }

        $filter = static::filterBinds($filter);

        $query     = self::$_connection->executeQuery(self::$_db . '.' . self::$_source, new \MongoDB\Driver\Query($filter, $options));
        $documents = [];
        foreach ($query as $i => $document) {
            $documents[$i] = new static();
            if ($options['projection']) {
                foreach ($documents[$i] as $key => $value) {
                    if ($key != '_id' && !$options['projection'][$key]) {
                        unset($documents[$i]->{$key});
                    }
                }
            }

            foreach ($document as $key => $value) {
                $documents[$i]->{$key} = $value;
            }
        }

        return $documents;
    }

    /**
     * findFirst
     *
     * @param  array|object $parameters
     * @return null|static
     */
    public static function findFirst($parameters = [])
    {
        self::execute();

        $options = [
            'limit' => 1,
        ];
        if (isset($parameters['sort'])) {
            $options['sort'] = $parameters['sort'];
        }

        if (isset($parameters['skip'])) {
            $options['skip'] = $parameters['skip'];
        }

        if (isset($parameters['projection'])) {
            $options['projection'] = \array_fill_keys($parameters['projection'], true);
        }

        $filter = [];
        if (isset($parameters[0]) && \is_array($parameters[0])) {
            $filter = $parameters[0];
        }

        $filter = static::filterBinds($filter);

        $query = self::$_connection->executeQuery(self::$_db . '.' . self::$_source, new \MongoDB\Driver\Query($filter, $options));
        foreach ($query as $document) {
            $static = new static();
            if ($options['projection']) {
                foreach ($static as $key => $value) {
                    if ($key != '_id' && !$options['projection'][$key]) {
                        unset($static->{$key});
                    }
                }
            }
            foreach ($document as $key => $value) {
                $static->{$key} = $value;
            }
            return $static;
        }

        return null;
    }

    /**
     * findById
     *
     * @param  null|string|\MongoDB\BSON\ObjectID $id
     * @return null|static
     */
    public static function findById($id)
    {
        self::execute();
        $filter = static::filterBinds([
            '_id' => self::objectId($id),
        ]);

        $query = self::$_connection->executeQuery(self::$_db . '.' . self::$_source, new \MongoDB\Driver\Query($filter, []));
        foreach ($query as $document) {
            $static = new static();
            foreach ($document as $key => $value) {
                $static->{$key} = $value;
            }
            return $static;
        }

        return null;
    }

    /**
     * count
     *
     * @param  array|object $parameters
     * @return integer
     */
    public static function count($parameters = [])
    {
        self::execute();

        $filter = [];
        if (isset($parameters[0]) && \is_array($parameters[0])) {
            $filter = $parameters[0];
        }

        $filter = static::filterBinds($filter);

        $query = self::$_connection->executeCommand(self::$_db, new \MongoDB\Driver\Command(['count' => self::$_source, 'query' => $filter]));
        return $query->toArray()[0]->n;
    }

    /**
     * sum
     *
     * @param  string $field
     * @param  array|object $filter
     * @return integer
     */
    public static function sum($field, $filter = [])
    {
        self::execute();

        $pipleLine = [];
        $filter    = self::filterBinds((array) $filter[0]);
        if (count($filter) > 0) {
            $pipleLine[] = [
                '$match' => $filter,
            ];
        }

        $pipleLine[] = [
            '$group' => [
                '_id'   => '$asdak',
                'total' => [
                    '$sum' => '$' . $field,
                ],
                'count' => [
                    '$sum' => 1,
                ],
            ],
        ];
        $query = self::$_connection->executeCommand(self::$_db, new \MongoDB\Driver\Command([
            'aggregate' => self::$_source,
            'pipeline'  => $pipleLine,
            "cursor"    => [
                "batchSize" => 1,
            ],
        ]));
        return $query->toArray()[0]->total;
    }

    /**
     * update
     *
     * @param  array|object $filter
     * @param  object $set
     * @param  object $options
     * @return bool
     */
    public static function update($filter = [], $set = [], $options = ['multi' => true, 'upsert' => false])
    {
        self::execute();
        $filter = static::filterBinds($filter);

        $query = new \MongoDB\Driver\BulkWrite;
        $query->update(
            $filter,
            ['$set' => $set],
            $options
        );
        $result = self::$_connection->executeBulkWrite(self::$_db . '.' . self::$_source, $query);
        return !!$result;
    }

    /**
     * insert
     *
     * @param  object $filter
     * @return string|bool
     */
    public static function insert($filter = [])
    {
        self::execute();
        $filter = static::filterBinds($filter);

        $query    = new \MongoDB\Driver\BulkWrite;
        $insertId = $query->insert($filter);
        $result   = self::$_connection->executeBulkWrite(self::$_db . '.' . self::$_source, $query);
        return $result ? $insertId : false;
    }

    /**
     * increment
     *
     * @param  object $filter
     * @param  object $inc
     * @param  object $options
     * @return bool
     */
    public static function increment($filter = [], $inc = [], $options = ['multi' => true, 'upsert' => false])
    {
        self::execute();
        $filter = static::filterBinds($filter);

        $query = new \MongoDB\Driver\BulkWrite;
        $query->update(
            $filter,
            ['$inc' => $inc],
            $options
        );
        $result = self::$_connection->executeBulkWrite(self::$_db . '.' . self::$_source, $query);
        return !!$result;
    }

    /**
     * removeColumns
     *
     * @param  object $filter
     * @param  object $unset
     * @param  object $options
     * @return bool
     */
    public static function removeColumns($filter = [], $unset = [], $options = ['multi' => true])
    {
        self::execute();
        $filter = static::filterBinds($filter);

        $query = new \MongoDB\Driver\BulkWrite;
        $query->update(
            $filter,
            ['$unset' => $unset],
            $options
        );
        $result = self::$_connection->executeBulkWrite(self::$_db . '.' . self::$_source, $query);
        return !!$result;
    }

    /**
     * renameColumns
     *
     * <code>
     *      Model::renameColumns([
     *          'is_deleted' => [
     *              '$ne' => 1
     *          ]
     *      ],[
     *          '<column name>' => true
     *      ]);
     * </code>
     * 
     *
     * @param  object $filter
     * @param  object $rename
     * @param  object $options
     * @return bool
     */
    public static function renameColumns($filter = [], $rename = [], $options = ['multi' => true])
    {
        self::execute();
        $filter = static::filterBinds($filter);

        $query = new \MongoDB\Driver\BulkWrite;
        $query->update(
            $filter,
            ['$rename' => $rename],
            $options
        );
        $result = self::$_connection->executeBulkWrite(self::$_db . '.' . self::$_source, $query);
        return !!$result;
    }

    /**
     * createIndexes
     *
     * <code>
     *      Model::createIndexes([
     *          [
     *              'name' => 'company_id',
     *              'key'  => [
     *                  'company_id' => 1
     *              ],
     *              'unique' => true,
     *              'expireAfterSeconds' => 300
     *          ]
     *      ]);
     * </code>
     *
     * @param  array $indexes
     * @return bool
     */
    public static function createIndexes($indexes = [])
    {
        self::execute();

        $ns     = self::$_db . '.' . self::$_source;
        $result = self::$_connection->executeCommand(self::$_db, new \MongoDB\Driver\Command([
            'createIndexes' => self::$_source,
            'indexes'       => \array_map(function ($row) use ($ns) {
                return \array_merge($row, [
                    'ns' => $ns,
                ]);
            }, $indexes),
        ]));

        return !!$result;
    }

    /**
     * deleteRaw
     *
     * @param  object $filter
     * @param  object $options
     * @return bool
     */
    public static function deleteRaw($filter = [], $options = ['limit' => 0])
    {
        self::execute();
        $filter = static::filterBinds($filter);

        $query = new \MongoDB\Driver\BulkWrite;
        $query->delete(
            $filter,
            $options
        );
        $result = self::$_connection->executeBulkWrite(self::$_db . '.' . self::$_source, $query);
        return !!$result;
    }

    /**
     * delete
     *
     * @return bool
     */
    public function delete()
    {
        if (!$this->getId()) {
            return false;
        }

        $this->beforeDelete();
        $res = self::deleteRaw([
            '_id' => self::objectId($this->getId()),
        ]);
        $this->afterDelete();
        return !!$res;
    }

    /**
     * save
     *
     * @param  bool $forceInsert
     * @return bool
     */
    public function save($forceInsert = false)
    {
        if (isset($this->_id) && !$this->_id instanceof \MongoDB\BSON\ObjectID) {
            $this->_id = self::objectId($this->_id);
        }

        if (!$this->_id || $forceInsert) {
            $this->beforeSave($forceInsert);
            $properties = (array) $this;
            if (!$forceInsert) {
                unset($properties['_id']);
            }
        } else {
            $this->beforeUpdate();
            $properties = (array) $this;
            unset($properties['_id']);
        }

        $properties = static::filterBinds($properties);

        if ($this->_id && !$forceInsert) {
            $result = self::update(['_id' => $this->_id], $properties);
            $this->afterSave($forceInsert);
        } else {
            $result    = self::insert($properties);
            $this->_id = self::objectId($result);
            $this->afterUpdate();
        }
        return !!$result;
    }

    /**
     * beforeUpdate
     *
     * @return void
     */
    public function beforeUpdate()
    {}

    /**
     * afterUpdate
     *
     * @return void
     */
    public function afterUpdate()
    {}

    /**
     * beforeSave
     *
     * @param  bool $forceInsert
     * @return void
     */
    public function beforeSave($forceInsert = false)
    {}

    /**
     * afterSave
     *
     * @return void
     */
    public function afterSave()
    {}

    /**
     * beforeDelete
     *
     * @return void
     */
    public function beforeDelete()
    {}

    /**
     * afterDelete
     *
     * @return void
     */
    public function afterDelete()
    {}

    /**
     * getId
     *
     * @return string
     */
    public function getId()
    {
        return (string) $this->_id;
    }

    /**
     * getIds
     *
     * @param  array<static> $documents
     * @return array<string>
     */
    public static function getIds($documents = [])
    {
        $data = [];
        foreach ($documents as $row) {
            $id = $row->getId();
            if (!in_array($id, $data)) {
                $data[] = $id;
            }
        }
        return $data;
    }

    /**
     * toArray
     *
     * @return object
     */
    public function toArray()
    {
        return self::objectToArray($this);
    }

    /**
     * objectToArray
     *
     * @param  object $data
     * @return array
     */
    public static function objectToArray($data)
    {
        $attributes = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $attributes[$key] = self::objectToArray($value);
            } elseif (is_object($value)) {
                if ($value instanceof \MongoDB\BSON\ObjectID) {
                    $attributes[$key] = (string) $value;
                } elseif ($value instanceof \MongoDB\BSON\UTCDateTime) {
                    $attributes[$key] = round($value->toDateTime()->format('U.u'), 0);
                } else {
                    $attributes[$key] = self::objectToArray($value);
                }
            } else {
                $attributes[$key] = $value;
            }
        }
        return $attributes;
    }

    /**
     * combineById
     *
     * @param array<static> $documents
     * @param mixed $callback
     * @return array<string,static>
     */
    public static function combineById($documents = [], $callback = false)
    {
        $data = [];
        foreach ($documents as $row) {
            $data[$row->getId()] = $callback && is_callable($callback) ? $callback($row) : $row;
        }
        return $data;
    }

    /**
     * combine
     *
     * @param string $key
     * @param array<array|static> $documents
     * @param boolean $eninqueKey
     * @param mixed $callback
     * @return array<string,static|array<static|mixed>>
     */
    public static function combine($key, $documents = [], $eninqueKey = false, $callback = false)
    {
        $data = [];
        foreach ($documents as $row) {
            if (\is_array($row)) {
                if (\array_key_exists($key, (array) $row)) {
                    if ($eninqueKey) {
                        $data[$row[$key]] = $callback && is_callable($callback) ? $callback($row) : $row;
                    } else {
                        $data[$row[$key]][] = $callback && is_callable($callback) ? $callback($row) : $row;
                    }
                }
            } elseif (\is_object($row)) {
                if (\property_exists($row, $key)) {
                    if ($row->{$key} instanceof \MongoDB\BSON\ObjectID) {
                        if ($eninqueKey) {
                            $data[$row->getId()] = $callback && is_callable($callback) ? $callback($row) : $row;
                        } else {
                            $data[$row->getId()][] = $callback && is_callable($callback) ? $callback($row) : $row;
                        }
                    } elseif ($row->{$key} instanceof \MongoDB\BSON\UTCDateTime) {
                        if ($eninqueKey) {
                            $data[round($row->{$key}->toDateTime()->format('U.u'), 0)] = $callback && is_callable($callback) ? $callback($row) : $row;
                        } else {
                            $data[round($row->{$key}->toDateTime()->format('U.u'), 0)][] = $callback && is_callable($callback) ? $callback($row) : $row;
                        }
                    } else {
                        if ($eninqueKey) {
                            $data[$row->{$key}] = $callback && is_callable($callback) ? $callback($row) : $row;
                        } else {
                            $data[$row->{$key}][] = $callback && is_callable($callback) ? $callback($row) : $row;
                        }
                    }
                }
            }
        }
        return $data;
    }

    /**
     * Convert ids to ObjectID
     *
     * @param  array $ids
     * @return array<\MongoDB\BSON\ObjectID>
     */
    public static function convertIds($ids = [])
    {
        return (array) array_map(function ($id) {
            return self::objectId($id);
        }, array_values(array_filter($ids, function ($id) {
            return !!self::isMongoId($id);
        })));
    }

    /**
     * Convert string _id to object id
     *
     * @param string $id
     * @return false|\MongoDB\BSON\ObjectID
     */
    public static function objectId($id)
    {
        if ($id instanceof \MongoDB\BSON\ObjectID) {
            return $id;
        } elseif (preg_match('/^[a-f\d]{24}$/i', $id)) {
            return new \MongoDB\BSON\ObjectID($id);
        }
        return null;
    }

    /**
     * Validation Mongo ID
     *
     * @param  mixed $id
     * @return bool
     * @throws \Exception|\MongoException
     */
    public static function isMongoId($id)
    {
        if (!$id) {
            return false;
        }

        if ($id instanceof \MongoDB\BSON\ObjectID || preg_match('/^[a-f\d]{24}$/i', $id)) {
            return true;
        }

        try {
            new \MongoDB\BSON\ObjectID($id);
            return true;
        } catch (\Exception$e) {
            return false;
        } catch (\MongoException$e) {
            return false;
        }
    }

    /**
     * Filter Mongo ID's
     *
     * @param  array $ids
     * @return array
     */
    public static function filterMongoIds($ids = [])
    {
        $data = [];
        foreach ($ids as $id) {
            if (self::isMongoId(trim($id))) {
                $data[] = trim($id);
            }
        }

        return $data;
    }

    /**
     * Filter
     *
     * @param  object|array $binds
     * @param  mixed $callback
     * @return object|array
     */
    public static function filter($binds = [], $callback = false)
    {
        if ($callback && is_callable($callback)) {
            return $callback($binds);
        }
        return $binds;
    }

    /**
     * Get mongo date by unixtime
     *
     * @param  integer|false $time
     * @param  bool $round
     * @return \MongoDB\BSON\UTCDatetime
     */
    public static function getDate($time = false, $round = true)
    {
        if (!$time) {
            $time = round(microtime(true) * 1000);
        } else if ($round) {
            $time *= 1000;
        }
        return new \MongoDB\BSON\UTCDateTime($time);
    }

    /**
     * Format mongo date to string
     *
     * @param  \MongoDB\BSON\UTCDateTime $date
     * @param  string $format
     * @return string
     */
    public static function dateFormat($date, $format = 'Y-m-d H:i:s')
    {
        return date($format, self::toSeconds($date));
    }

    /**
     * Conver mongo date to unixtime
     *
     * @param  \MongoDB\BSON\UTCDateTime $date
     * @return integer
     */
    public static function toSeconds($date)
    {
        if ($date && \method_exists($date, 'toDateTime')) {
            return round(@$date->toDateTime()->format('U.u'), 0);
        }
        return 0;
    }

    /**
     * toTime
     *
     * @param  string $property
     * @return integer
     */
    public function toTime($property)
    {
        if (!\property_exists($this, $property)) {
            $reflection = new ReflectionClass(get_class($this));
            throw new Exception("Property " . $property . " does not exist in " . $reflection->getNamespaceName());
        }
        return self::toSeconds($this->{$property});
    }

    /**
     * toDate
     *
     * @param  string $property
     * @param  string $format
     * @return string
     */
    public function toDate($property, $format = 'Y-m-d H:i:s')
    {
        if (!\property_exists($this, $property)) {
            $reflection = new ReflectionClass(get_class($this));
            throw new Exception("Property " . $property . " does not exist in " . $reflection->getNamespaceName());
        }
        return self::dateFormat($this->{$property}, $format);
    }

    /**
     * execute
     *
     * @return void
     * @throws Exception
     */
    public static function execute()
    {
        $db = static::getDB();
        if (!$db) {
            throw new Exception('Database not found');
        }

        $server = static::getServer();
        if (!$server) {
            throw new Exception('MongoDB server not found');
        }

        $source = static::getSource();
        if (!$source) {
            throw new Exception('Collection not found');
        }

        self::setServer($server);
        self::setDb($db);
        self::setSource($source);

        if (!isset($_connection)) {
            self::connect();
        }
    }

    /**
     * connect
     *
     * @return void
     */
    public static function connect()
    {
        if (!self::$_server['username'] || !self::$_server['password']) {
            $dsn = 'mongodb://' . self::$_server['host'] . ':' . self::$_server['port'];
        } else {
            $dsn = sprintf(
                'mongodb://%s:%s@%s',
                self::$_server['username'],
                self::$_server['password'],
                self::$_server['host']
            );
        }
        self::$_connection = new \MongoDB\Driver\Manager($dsn);
    }

    /**
     * getConnection
     *
     * @return \MongoDB\Driver\Manager
     */
    public static function getConnection()
    {
        return self::$_connection;
    }

    /**
     * setServer
     *
     * @param  object $server
     * @return void
     */
    public static function setServer($server = [])
    {
        self::$_server = $server;
    }

    /**
     * getServer
     *
     * @return object
     */
    public static function getServer()
    {
        return self::$_server;
    }

    /**
     * setDb
     *
     * @param  string $db
     * @return void
     */
    public static function setDb($db)
    {
        self::$_db = $db;
    }

    /**
     * getDb
     *
     * @return string
     */
    public static function getDb()
    {
        return self::$_db;
    }

    /**
     * setSource
     *
     * @param  string $source
     * @return void
     */
    public static function setSource($source)
    {
        self::$_source = $source;
    }

    /**
     * getSource
     *
     * @return string
     */
    public static function getSource()
    {
        return self::$_source;
    }

    /**
     * filterBinds
     *
     * @param  object|array $filter
     * @return object|array
     */
    public static function filterBinds($filter = [])
    {
        return $filter;
    }
    
    /**
     * __get
     *
     * @param  string $property
     * @return mixed
     */
    public function __get($property)
    {
        if(!property_exists($this, $property)) {
            $this->{$property} = null;
        }
        return $this->{$property};
    }
    
    /**
     * __set
     *
     * @param  string $property
     * @param  mixed $value
     * @return void
     */
    public function __set($property, $value)
    {
        $this->{$property} = $value;
    }
}
