<?php

namespace WPCustomTables;

class Repository
{
    /**
     * The current table name
     *
     * @var boolean
     */
    protected $tableName = false;
    protected $unprefixedTableName = false;
    protected $primaryKey = false;

    /**
     * Constructor for the database class to inject the table name
     *
     * @param String $tableName - The current table name
     */
    public function __construct()
    {
        global $wpdb;
        $this->unprefixedTableName = $this->tableName;
        $this->tableName = $wpdb->prefix . ($this->tableName);
    }

    /**
     * Insert data into the current data
     *
     * @param  array  $data - Data to enter into the database table
     *
     * @return InsertQuery Object
     */
    public function insert(array $data)
    {
        global $wpdb;

        if (empty($data)) {
            return false;
        }

        $wpdb->insert($this->tableName, $data);

        return $wpdb->insert_id;
    }

    /**
     * Get all from the selected table
     *
     * @param  String $orderBy - Order by column name
     *
     * @return Table result
     */
    public function getAll($orderBy = null, $perPage = null, $page = null)
    {
        global $wpdb;

        $sql = 'SELECT * FROM `'.$this->tableName.'`';

        if (!empty($orderBy)) {
            $sql .= ' ORDER BY ' . $orderBy;
        }

        $sql = $this->addLimit($sql, $perPage, $page);

        $all = $wpdb->get_results($sql);

        return $all;
    }

    /**
     * Get a value by a condition
     *
     * @param  Array $conditionValue - A key value pair of the conditions you want to search on
     * @param  String $condition - A string value for the condition of the query default to equals
     *
     * @return Table result
     */
    public function getBy(array $conditionValue, $condition = '=', $perPage = null, $page = null)
    {
        global $wpdb;

        $sql = 'SELECT * FROM `'.$this->tableName.'` WHERE ';

        foreach ($conditionValue as $field => $value) {
            switch (strtolower($condition)) {
                case 'in':
                    if (!is_array($value)) {
                        throw new Exception("Values for IN query must be an array.", 1);
                    }

                    $sql .= $wpdb->prepare('`%s` IN (%s)', $field, implode(',', $value));
                    break;

                default:
                    $sql .= $wpdb->prepare('`'.$field.'` '.$condition.' %s', $value);
                    break;
            }
        }

        $sql = $this->addLimit($sql, $perPage, $page);

        $result = $wpdb->get_results($sql);

        return $result;
    }

    protected function addLimit($sql, $perPage, $page)
    {
        if (!empty($perPage)) {
            $sql .= $wpdb->prepare(' LIMIT %d', $perPage);
        }
        if (!empty($page)) {
            $sql .= $wpdb->prepare(' OFFSET %d', $page * ($perPage ?? 1));
        }
        return $sql;
    }

    /**
     * Update a table record in the database
     *
     * @param  array  $data           - Array of data to be updated
     * @param  array  $conditionValue - Key value pair for the where clause of the query
     *
     * @return Updated object
     */
    public function update(array $data, array $conditionValue)
    {
        global $wpdb;

        if (empty($data)) {
            return false;
        }

        $updated = $wpdb->update($this->tableName, $data, $conditionValue);

        return $updated;
    }

    /**
     * Delete row on the database table
     *
     * @param  array  $conditionValue - Key value pair for the where clause of the query
     *
     * @return Int - Num rows deleted
     */
    public function delete(array $conditionValue)
    {
        global $wpdb;

        $deleted = $wpdb->delete($this->tableName, $conditionValue);

        return $deleted;
    }

    public function get($id, $output = OBJECT)
    {
        if (!$this->primaryKey) {
            return false;
        }

        global $wpdb;

        $sql = 'SELECT * FROM `' . $this->tableName . '` WHERE ' . $this->primaryKey . ' = ' . $id;

        return $wpdb->get_row($sql, $output);
    }

    public function query()
    {
        return QueryBuilder::table("$this->tableName as $this->unprefixedTableName");
    }
}
