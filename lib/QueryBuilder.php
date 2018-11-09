<?php

namespace WPCustomTables;

use WPCustomTables\RawExpression;

class QueryBuilder
{
    protected $from;
    protected $select = [];
    protected $where = [];
    protected $order;
    protected $orderBy;
    protected $skip;
    protected $take;
    protected $join;

    public static function table($tableName, $db = null)
    {
        return (new self($db))->from($tableName);
    }

    public function __construct($db = null)
    {
        $this->db = $db ?? $GLOBALS['wpdb'];
    }

    public function from($tableName)
    {
        $this->from = $tableName;
        return $this;
    }

    public function select(...$columns)
    {
        $this->select = $columns;
        return $this;
    }

    public function addSelect(...$columns)
    {
        $this->select = array_merge($this->select, $columns);
        return $this;
    }

    protected function normalizeWhereConditions($conditions, $operator, $joiner, $valueIsColumn = false)
    {
        return array_map(function ($condition) use ($operator, $joiner, $valueIsColumn) {
            if (is_callable($condition)) {
                return $condition;
            }
            if (count($condition) === 1) {
                $condition[] = null;
            }
            if (count($condition) === 2) {
                $condition = [$condition[0], $operator, $condition[1]];
            }
            if (count($condition) === 3) {
                $condition[] = $this->realEscape($joiner);
            }
            if (count($condition) === 4) {
                $condition[] = $valueIsColumn;
            }
            return $condition;
        }, $conditions);
    }

    public function where(...$conditions)
    {
        if (!is_array($conditions[0])) {
            $conditions = [$conditions];
        }
        $conditions = $this->normalizeWhereConditions($conditions, '=', 'AND');

        $this->where = array_merge($this->where, $conditions);
        return $this;
    }

    public function orWhere(...$conditions)
    {
        if (!is_array($conditions[0])) {
            $conditions = [$conditions];
        }
        $conditions = $this->normalizeWhereConditions($conditions, '=', 'OR');
        $this->where = array_merge($this->where, $conditions);
        return $this;
    }

    public function whereIn(...$conditions)
    {
        if (!is_array($conditions[0])) {
            $conditions = [$conditions];
        }
        $conditions = $this->normalizeWhereConditions($conditions, 'IN', 'AND');
        $this->where = array_merge($this->where, $conditions);
        return $this;
    }

    public function whereNotIn(...$conditions)
    {
        if (!is_array($conditions[0])) {
            $conditions = [$conditions];
        }
        $conditions = $this->normalizeWhereConditions($conditions, 'NOT IN', 'AND');
        $this->where = array_merge($this->where, $conditions);
        return $this;
    }

    public function whereNull(...$conditions)
    {
        if (!is_array($conditions[0])) {
            $conditions = [$conditions];
        }
        $conditions = $this->normalizeWhereConditions($conditions, 'IS', 'AND');
        $this->where = array_merge($this->where, $conditions);
        return $this;
    }

    public function whereNotNull(...$conditions)
    {
        if (!is_array($conditions[0])) {
            $conditions = [$conditions];
        }
        $conditions = $this->normalizeWhereConditions($conditions, 'IS NOT', 'AND');
        $this->where = array_merge($this->where, $conditions);
        return $this;
    }

    public function whereColumn(...$conditions)
    {
        if (!is_array($conditions[0])) {
            $conditions = [$conditions];
        }
        $conditions = $this->normalizeWhereConditions($conditions, '=', 'AND', true);

        $this->where = array_merge($this->where, $conditions);
        return $this;
    }

    public function orderBy($orderBy)
    {
        $this->orderBy = $orderBy;
        return $this;
    }

    public function order($order)
    {
        $this->order = strtolower($order);
        return $this;
    }

    public function groupBy($groupBy)
    {
        $this->groupBy = $groupBy;
        return $this;
    }

    public function having($having)
    {
        $this->having = $having;
        return $this;
    }

    public function skip($skip)
    {
        $this->skip = $skip;
        return $this;
    }

    public function take($take)
    {
        $this->take = $take;
        return $this;
    }

    public function limit($take)
    {
        return $this->take($take);
    }

    public function offset($skip)
    {
        return $this->skip($skip);
    }

    public function join($table, $leftColumn, $operator = null, $rightColumn = null)
    {
        $join = [$table, $leftColumn, $operator, $rightColumn, 'JOIN'];

        $this->join[] = $join;
        return $this;
    }

    public function leftJoin($table, $leftColumn, $operator = null, $rightColumn = null)
    {
        $join = [$table, $leftColumn, $operator, $rightColumn, 'LEFT JOIN'];

        $this->join[] = $join;
        return $this;
    }

    public function get($buildOnly = false)
    {
        $query = $this->buildQuery();
        return $buildOnly ? $query : $this->db->get_results($query);
    }

    public function first($buildOnly = false)
    {
        $query = $this->buildQuery();
        return $buildOnly ? $query : $this->db->get_row($query);
    }

    public function count($countArgument = '*', $runQuery = true)
    {
        $countQuery = clone $this;
        $countQuery->select(new RawExpression("COUNT($countArgument)"));
        if ($runQuery) {
            $query = $countQuery->buildQuery();
            return (int)$countQuery->db->get_var($query);
        } else {
            return $countQuery;
        }
    }

    public function update($assignments, $buildOnly = false)
    {
        $query = $this->buildUpdateQuery($assignments);
        return $buildOnly ? $query : $this->db->query($query);
    }

    public function buildQuery()
    {
        $sql[] = $this->buildSelect();
        $sql[] = $this->buildFrom();
        $sql[] = $this->buildJoin();
        $sql[] = $this->buildWhere();
        $sql[] = $this->buildGroupBy();
        $sql[] = $this->buildOrderBy();
        $sql[] = $this->buildLimit();

        return implode(' ', array_filter($sql));
    }

    public function buildUpdateQuery($assignments)
    {
        $sql[] = 'UPDATE ' . $this->escapeWithAlias($this->from);
        $sql[] = $this->buildJoin();
        $sql[] = $this->buildSet($assignments);
        $sql[] = $this->buildWhere();
        $sql[] = $this->buildOrderBy();
        $sql[] = $this->buildLimit();

        return implode(' ', array_filter($sql));
    }

    public function buildSelect()
    {
        $sql = 'SELECT ';
        $params = [];
        if (!empty($this->select)) {
            $placeholders = array_map(function ($select) {
                return $this->escapeWithAlias($select);
            }, $this->select);
            $sql .= implode(', ', $placeholders);
        } else {
            $sql .= '*';
        }
        return $this->prepare($sql, $params);
    }

    public function buildFrom()
    {
        return 'FROM ' . $this->escapeWithAlias($this->from);
    }

    public function buildJoin()
    {
        if (!empty($this->join)) {
            $joins = array_map(function ($join) {
                list($table, $leftColumn, $operator, $rightColumn, $joinType) = $join;
                $query = new static($this->db);
                $query->whereColumn([$leftColumn, $operator, $rightColumn]);
                return $this->realEscape($joinType) . ' ' . $this->escapeWithAlias($table) . ' ON ' . $query->buildWhere(true);
            }, $this->join);
            return implode(' ', $joins);
        }
    }

    public function buildWhere($skipWhere = false)
    {
        $params = [];
        if (!empty($this->where)) {
            $whereClauses = array_reduce($this->where, function ($carry, $whereClause) use (&$params) {
                list($column, $operator, $value, $joiner, $valueIsColumn) = $whereClause;
                $operator = $this->realEscape($operator);
                $joiner = $this->realEscape($joiner);
                if (!empty($carry)) {
                    $carry .= " $joiner ";
                }
                if (is_callable($column)) {
                    $query = new static($this->db);
                    call_user_func($column, $query);
                    $where = $query->buildWhere(true);
                    if (!empty($where)) {
                        $carry .= "($where)";
                    }
                } else {
                    if ($valueIsColumn) {
                        $carry .= $this->escapeSqlId($column) . " $operator " . $this->escapeSqlId($value);
                    } else {
                        $placeholder = $this->getDbPlaceholder($value);
                        if (is_null($placeholder)) {
                            $placeholder = 'NULL';
                        } elseif ($placeholder !== $value) {
                            $params[] = $value;
                        } else {
                            $placeholder = $this->realEscape($placeholder);
                        }
                        $carry .= $this->escapeSqlId($column) . " $operator $placeholder";
                    }
                }
                return $carry;
            }, '');
            $output = $this->prepare($whereClauses, $params);
            if (!$skipWhere) {
                $output = "WHERE $output";
            }
            return $output;
        }
    }

    public function buildOrderBy()
    {
        if (!empty($this->orderBy)) {
            return 'ORDER BY ' . $this->escapeSqlId($this->orderBy) . ' ' . ($this->order === 'desc' ? 'DESC' : 'ASC');
        }
    }

    public function buildGroupBy()
    {
        if (!empty($this->groupBy)) {
            if (is_array($this->groupBy)) {
                $groupBy = implode(', ', array_map([$this, 'escapeSqlId'], $this->groupBy));
            } else {
                $groupBy = $this->escapeSqlId($this->groupBy);
            }
            return 'GROUP BY ' . $groupBy;
        }
    }

    public function buildLimit()
    {
        if (!empty($this->take)) {
            $params = [];
            $sql = 'LIMIT %d ';
            $params[] = $this->take;

            if (!empty($this->skip)) {
                $sql .= ' OFFSET %d ';
                $params[] = $this->skip;
            }
            return $this->prepare($sql, $params);
        }
    }

    public function buildSet($assignments)
    {
        $setClauses = [];
        $params = [];
        foreach ($assignments as $column => $value) {
            $placeholder = $this->getDbPlaceholder($value);
            if (is_null($placeholder)) {
                $placeholder = 'NULL';
            } elseif ($placeholder !== $value) {
                $params[] = $value;
            } else {
                $placeholder = $this->realEscape($placeholder);
            }
            $setClauses[] = $this->escapeSqlId($column) . " = $placeholder";
        }
        $output = $this->prepare(implode(', ', $setClauses), $params);
        return "SET $output";
    }

    public function prepare($sql, $params = null)
    {
        if (empty($params)) {
            return $sql;
        } else {
            return $this->db->prepare($sql, $params);
        }
    }

    public function escapeWithAlias($string)
    {
        if ($string instanceof RawExpression) {
            return $this->realEscape($string);
        }
        $aliasString = ' as ';
        $aliasStringPosition = stripos($string, $aliasString);
        if ($aliasStringPosition) {
            $tablename = substr($string, 0, $aliasStringPosition);
            $alias = substr($string, $aliasStringPosition + strlen($aliasString), strlen($string));
            return $this->escapeSqlId($tablename) . ' ' . $this->escapeSqlId($alias);
        } else {
            return $this->escapeSqlId($string);
        }
    }

    public function escapeSqlId($string)
    {
        if ($string instanceof RawExpression) {
            return $this->realEscape($string);
        }
        $strings = explode('.', $string);
        $strings = array_map(function ($string) {
            if ($string === '*') {
                return $string;
            } else {
                return '`' . str_replace("`", "``", $this->realEscape($string)) . '`';
            }
        }, $strings);
        return implode('.', $strings);
    }

    protected function realEscape($string)
    {
        if ($string instanceof RawExpression) {
            return $string;
        }
        if ($this->db->dbh) {
            if ($this->db->use_mysqli) {
                return mysqli_real_escape_string($this->db->dbh, $string);
            } else {
                return mysql_real_escape_string($string, $this->db->dbh);
            }
        }
        return addslashes($string);
    }

    public function getDbPlaceholder($value)
    {
        if (is_string($value)) {
            return '%s';
        } elseif (is_int($value) || is_bool($value)) {
            return '%d';
        } elseif (is_float($value)) {
            return '%f';
        } else {
            return $value;
        }
    }
}
