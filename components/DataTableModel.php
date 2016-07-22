<?php

namespace components;


use trident\Request;

abstract class DataTableModel
{
    /**
     * @var $db \PDO
     */
    protected $db;

    public function __construct(\PDO $db)//
    {
        $this->db = $db;
    }

    /**
     * Perform the SQL queries needed for an server-side processing requested,
     * utilising the helper functions of this class, limit(), order() and
     * filter() among others. The returned array is ready to be encoded as JSON
     * in response to an SSP request, or can be modified if needed before
     * sending back to the client.
     *
     * @param  Request $request Data sent to server by DataTables
     * @return array          Server-side processing response array
     */
    public function simple(Request $request)
    {
        $table = $this->getTableName();
        $primaryKey = $this->getPK();

        $columns = $this->getColumns();
        $bindings = array();
        // Build the SQL query string from the request
        $limit = $this->limit($request, $columns);
        $order = $this->order($request, $columns);
        $where = $this->filter($request, $columns, $bindings);
        // Main query to actually get the data
        $data = $this->sql_exec(
            $bindings,
            "SELECT `".implode("`, `", $this->pluck($columns, 'db'))."`
			 FROM `$table`
			 $where
			 $order
			 $limit"
        );
        // Data set length after filtering
        $resFilterLength = $this->sql_exec(
            $bindings,
            "SELECT COUNT(`{$primaryKey}`)
			 FROM   `$table`
			 $where"
        );
        $recordsFiltered = $resFilterLength[0][0];
        // Total data set length
        $resTotalLength = $this->sql_exec(
            "SELECT COUNT(`{$primaryKey}`)
			 FROM   `$table`"
        );
        $recordsTotal = $resTotalLength[0][0];

        /*
         * Output
         */

        return array(
            "draw" => $request->param('draw', 0),
            "recordsTotal" => intval($recordsTotal),
            "recordsFiltered" => intval($recordsFiltered),
            "data" => $this->data_output($columns, $data),
        );
    }

    /**
     * @return string Table name
     */
    abstract public function getTableName();

    /**
     * @return string Primary key
     */
    abstract public function getPK();

    /**
     * @return array Column information array
     */
    abstract public function getColumns();

    /**
     * Paging
     *
     * Construct the LIMIT clause for server-side processing SQL query
     *
     * @param  Request $request Data sent to server by DataTables
     * @param  array $columns Column information array
     * @return string SQL limit clause
     */
    protected function limit(Request $request, $columns)
    {
        $start = $request->param('start', null);
        $limit = '';
        if (null !== $start && -1 != $start) {
            $limit = "LIMIT ".intval($start).", ".intval($request->param('length', 1));
        }

        return $limit;
    }

    /**
     * Ordering
     *
     * Construct the ORDER BY clause for server-side processing SQL query
     *
     * @param  Request $request Data sent to server by DataTables
     * @param  array $columns Column information array
     * @return string SQL order by clause
     */
    protected function order(Request $request, $columns)
    {
        $sql = '';
        $order = $request->param('order', null);
        if (null !== $order && count($order)) {
            $orderBy = array();
            $dtColumns = self::pluck($columns, 'dt');
            for ($i = 0, $ien = count($order); $i < $ien; $i++) {
                // Convert the column index into the column data property
                $columnIdx = intval($request->param("order.$i.column"));
                $requestColumn = $request->param("columns.$columnIdx");
                $columnIdx = array_search($requestColumn['data'], $dtColumns);
                $column = $columns[$columnIdx];
                if ($requestColumn['orderable'] == 'true') {
                    $dir = $request->param("order.$i.dir") === 'asc' ?
                        'ASC' :
                        'DESC';
                    $orderBy[] = '`'.$column['db'].'` '.$dir;
                }
            }
            if(empty($orderBy)){
                return '';
            }
            $sql = 'ORDER BY '.implode(', ', $orderBy);
        }

        return $sql;
    }

    /**
     * Pull a particular property from each assoc. array in a numeric array,
     * returning and array of the property values from each item.
     *
     * @param  array $a Array to get data from
     * @param  string $prop Property to read
     * @return array        Array of property values
     */
    protected function pluck($a, $prop)
    {
        $out = array();
        for ($i = 0, $len = count($a); $i < $len; $i++) {
            $out[] = $a[$i][$prop];
        }

        return $out;
    }

    /**
     * Searching / Filtering
     *
     * Construct the WHERE clause for server-side processing SQL query.
     *
     * NOTE this does not match the built-in DataTables filtering which does it
     * word by word on any field. It's possible to do here performance on large
     * databases would be very poor
     *
     * @param  Request $request Data sent to server by DataTables
     * @param  array $columns Column information array
     * @param  array $bindings Array of values for PDO bindings, used in the
     *    sql_exec() function
     * @return string SQL where clause
     */
    protected function filter($request, $columns, &$bindings)
    {
        $globalSearch = array();
        $columnSearch = array();
        $dtColumns = self::pluck($columns, 'dt');
        $search = $request->param('search', null);
        $str = $request->param('search.value', '');
        $columnsFromRequest = $request->param('columns', []);
        if (null !== $search && !empty($str)) {
            for ($i = 0, $ien = count($columnsFromRequest); $i < $ien; $i++) {
                $requestColumn = $columnsFromRequest[$i];
                $columnIdx = array_search($requestColumn['data'], $dtColumns);
                $column = $columns[$columnIdx];
                if ($requestColumn['searchable'] == 'true') {
                    $binding = $this->bind($bindings, '%'.$str.'%', \PDO::PARAM_STR);
                    $globalSearch[] = "`".$column['db']."` LIKE ".$binding;
                }
            }
        }
        // Individual column filtering
        if (!empty($columnsFromRequest)) {
            for ($i = 0, $ien = count($columnsFromRequest); $i < $ien; $i++) {
                $requestColumn = $columnsFromRequest[$i];
                $columnIdx = array_search($requestColumn['data'], $dtColumns);
                $column = $columns[$columnIdx];
                $str = $requestColumn['search']['value'];
                if ($requestColumn['searchable'] == 'true' &&
                    $str != ''
                ) {
                    $binding = $this->bind($bindings, '%'.$str.'%', \PDO::PARAM_STR);
                    $columnSearch[] = "`".$column['db']."` LIKE ".$binding;
                }
            }
        }
        // Combine the filters into a single string
        $where = '';
        if (count($globalSearch)) {
            $where = '('.implode(' OR ', $globalSearch).')';
        }
        if (count($columnSearch)) {
            $where = $where === '' ?
                implode(' AND ', $columnSearch) :
                $where.' AND '.implode(' AND ', $columnSearch);
        }
        if ($where !== '') {
            $where = 'WHERE '.$where;
        }

        return $where;
    }

    /**
     * Create a PDO binding key which can be used for escaping variables safely
     * when executing a query with sql_exec()
     *
     * @param  array &$a Array of bindings
     * @param  *      $val  Value to bind
     * @param  int $type PDO field type
     * @return string       Bound key to be used in the SQL where this parameter
     *   would be used.
     */
    protected function bind(&$a, $val, $type)
    {
        $key = ':binding_'.count($a);
        $a[] = array(
            'key' => $key,
            'val' => $val,
            'type' => $type,
        );

        return $key;
    }

    /**
     * Execute an SQL query on the database
     *
     * @param  resource $db Database handler
     * @param  array $bindings Array of PDO binding values from bind() to be
     *   used for safely escaping strings. Note that this can be given as the
     *   SQL query string if no bindings are required.
     * @param  string $sql SQL query to execute.
     * @return array         Result from the query (all rows)
     */
    protected function sql_exec($bindings, $sql = null)
    {
        // Argument shifting
        if ($sql === null) {
            $sql = $bindings;
        }
        //echo $sql;
        $stmt = $this->db->prepare($sql);

        // Bind parameters
        if (is_array($bindings)) {
            for ($i = 0, $ien = count($bindings); $i < $ien; $i++) {
                $binding = $bindings[$i];
                $stmt->bindValue($binding['key'], $binding['val'], $binding['type']);
            }
        }
        // Execute
        try {
            $stmt->execute();
        } catch (\PDOException $e) {
            return ["error" => "An SQL error occurred: ".$e->getMessage()];
        }

        // Return all
        return $stmt->fetchAll(\PDO::FETCH_BOTH);
    }

    /**
     * Create the data output array for the DataTables rows
     *
     * @param  array $columns Column information array
     * @param  array $data Data from the SQL get
     * @return array          Formatted data in a row based format
     */
    protected function data_output($columns, $data)
    {
        $out = array();
        for ($i = 0, $ien = count($data); $i < $ien; $i++) {
            $row = array();
            for ($j = 0, $jen = count($columns); $j < $jen; $j++) {
                $column = $columns[$j];
                // Is there a formatter?
                if (isset($column['formatter'])) {
                    $row[$column['dt']] = $column['formatter']($data[$i][$column['db']], $data[$i]);
                } else {
                    $row[$column['dt']] = $data[$i][$columns[$j]['db']];
                }
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * The difference between this method and the `simple` one, is that you can
     * apply additional `where` conditions to the SQL queries. These can be in
     * one of two forms:
     *
     * * 'Result condition' - This is applied to the result set, but not the
     *   overall paging information query - i.e. it will not effect the number
     *   of records that a user sees they can have access to. This should be
     *   used when you want apply a filtering condition that the user has sent.
     * * 'All condition' - This is applied to all queries that are made and
     *   reduces the number of records that the user can access. This should be
     *   used in conditions where you don't want the user to ever have access to
     *   particular records (for example, restricting by a login id).
     *
     * @param  Request $request Data sent to server by DataTables
     * @param  string $whereResult WHERE condition to apply to the result set
     * @param  string $whereAll WHERE condition to apply to all queries
     * @return array          Server-side processing response array
     */
    public function complex(Request $request, $whereResult = null, $whereAll = null)
    {
        $table = $this->getTableName();
        $primaryKey = $this->getPK();
        $columns = $this->getColumns();

        $bindings = $localWhereResult = $localWhereAll = [];
        $whereAllSql = '';
        // Build the SQL query string from the request
        $limit = $this->limit($request, $columns);
        $order = $this->order($request, $columns);
        $where = $this->filter($request, $columns, $bindings);
        $whereResult = $this->flatten($whereResult);
        $whereAll = $this->flatten($whereAll);
        if ($whereResult) {
            $where = $where ?
                $where.' AND '.$whereResult :
                'WHERE '.$whereResult;
        }
        if ($whereAll) {
            $where = $where ?
                $where.' AND '.$whereAll :
                'WHERE '.$whereAll;
            $whereAllSql = 'WHERE '.$whereAll;
        }
        // Main query to actually get the data
        $data = $this->sql_exec(
            $bindings,
            "SELECT `".implode("`, `", $this->pluck($columns, 'db'))."`
			 FROM `$table`
			 $where
			 $order
			 $limit"
        );
        // Data set length after filtering
        $resFilterLength = $this->sql_exec(
            $bindings,
            "SELECT COUNT(`{$primaryKey}`)
			 FROM   `$table`
			 $where"
        );
        $recordsFiltered = $resFilterLength[0][0];
        // Total data set length
        $resTotalLength = $this->sql_exec(
            $bindings,
            "SELECT COUNT(`{$primaryKey}`)
			 FROM   `$table` ".
            $whereAllSql
        );
        $recordsTotal = $resTotalLength[0][0];

        /*
         * Output
         */

        return array(
            "draw" => $request->param('draw', 0),
            "recordsTotal" => intval($recordsTotal),
            "recordsFiltered" => intval($recordsFiltered),
            "data" => $this->data_output($columns, $data),
        );
    }

    /**
     * Return a string from an array or a string
     *
     * @param  array|string $a Array to join
     * @param  string $join Glue for the concatenation
     * @return string Joined string
     */
    protected function flatten($a, $join = ' AND ')
    {
        if (!$a) {
            return '';
        } else {
            if ($a && is_array($a)) {
                return implode($join, $a);
            }
        }

        return $a;
    }
}