<?php

/**
 * Main connection class. Mostly based on Vizzuality's CartoDB PHP class
 * https://github.com/Vizzuality/cartodbclient-php
 * 
 * @author tiagojsag
 */

namespace Simbiotica\CartoDBClient;

use Eher\OAuth\Request;
use Eher\OAuth\Consumer;
use Eher\OAuth\Token;
use Eher\OAuth\HmacSha1;
use Eher\OAuth;

abstract class Connection
{
    const SESSION_KEY_SEED = "cartodb";
    
    /**
     * Object to store token
     **/
    protected $storage;
    
    /**
     * Necessary data to connect to CartoDB
     */
    protected $subdomain;
    
    /**
     * Internal variables
     */
    public $authorized = false;
    public $json_decode = true;
    
    /**
     * Endpoint urls
     */
    protected $oauthUrl;
    protected $apiUrl;

    function __construct(TokenStorageInterface $storage, $subdomain)
    {
        $this->storage = $storage;
        $this->subdomain = $subdomain;
        $this->apiUrl = sprintf('https://%s.cartodb.com/api/v2/', $this->subdomain);
        
        $this->authorized = $this->getAccessToken();
    }
    
    abstract protected function getAccessToken();
    
    public function runSql($sql)
    {
        $params = array('q' => $sql);
        $payload = $this->request('sql', 'POST', array('params' => $params));

        $info = $payload->getInfo();
        $rawResponse = $payload->getRawResponse();
        if ($info['http_code'] != 200) {
            if (!empty($rawResponse['return']['error']))
                throw new \RuntimeException(sprintf(
                    'There was a problem with your CartoDB request "%s": %s',
                    $payload->getRequest()->__toString(),
                    implode('<br>', $rawResponse['return']['error'])));
            else
                throw new \RuntimeException(sprintf(
                    'There was a problem with your CartoDB request "%s"',
                    $payload->getRequest()->__toString()));
        }
        
        return $payload;
    }

    /**
     * API v2 - Not officialy supported
     * 
     * Creates a new table
     * Warning: tables created this way will NOT show up on your CartoDB dashboard
     * Also, several features, like created_at and updated_at will not work
     * automatically
     * 
     * @param unknown $table The name of the table
     * @param array $schema Array with ($column_name => $type) pairs
     */
    public function createTable($table, array $schema)
    {
        $schema = array_merge(array(
                'cartodb_id' => 'serial',
        ), $schema);
        
        if ($schema) {
            $cols = array();
            foreach ($schema as $key => $value) {
                $cols[] = "$key $value";
            }
            $sql = sprintf("CREATE TABLE IF NOT EXISTS %s (%s)", $table, implode(", ", $cols));
        }
        else
            $sql = sprintf("CREATE TABLE IF NOT EXISTS %s", $table);
        
        return $this->runSql($sql);
    }
    
    /**
     * API v2 - Not officialy supported
     * 
     * Retrieves metadata about the table columns
     * 
     * @param unknown $table Table name
     * @param boolean $full If true, retrieves full column information. Defaults to false,
     * which only returns the names
     */
    public function showTable($table, $full = false)
    {
        if ($full)
            $sql = sprintf("SELECT * FROM information_schema.columns WHERE table_name ='%s'", $table);
        else
            $sql = sprintf("SELECT column_name FROM information_schema.columns WHERE table_name ='%s'", $table);
    
        return $this->runSql($sql);
    }

    /**
     * API v2 - Not officialy supported
     *
     * Deletes a table
     *
     * @param unknown $table Table name
     */
    public function dropTable($table)
    {
        $sql = sprintf("DROP TABLE IF EXISTS %s", $table);
    
        return $this->runSql($sql);
    }
    
    /**
     * API v2 - Not officialy supported
     *
     * Deletes all rows from a table
     *
     * @param unknown $table Table name
     */
    public function truncateTable($table, $restartId = true)
    {
        if ($restartId)
            $sql = sprintf("TRUNCATE TABLE %s RESTART IDENTITY", $table);
        else
            $sql = sprintf("TRUNCATE TABLE %s CONTINUE IDENTITY", $table);
    
        return $this->runSql($sql);
    }

    /**
     * API v2 - Not officialy supported
     * 
     * Adds a column to an existing table
     * 
     * @param unknown $table The table name
     * @param unknown $column_name Name of the column to be added
     * @param unknown $column_type Type of the column to be added
     */
    public function addColumn($table, $column_name, $column_type)
    {
        $sql = sprintf("ALTER TABLE %s ADD COLUMN %s %s", $table, $column_name, $column_type);
    
        return $this->runSql($sql);
    }

    /**
     * API v2 - Not officialy supported
     * 
     * Deletes a column from an existing table
     * 
     * @param unknown $table The table name
     * @param unknown $column_name 
     */
    public function dropColumn($table, $column_name)
    {
        $sql = sprintf("ALTER TABLE %s DROP COLUMN %s", $table, $column_name);
    
        return $this->runSql($sql);
    }

    /**
     * API v2 - Not officialy supported
     * 
     * Rename a column
     * 
     * @param unknown $table Table name
     * @param unknown $column_name Column current name
     * @param unknown $new_column_name Column new name
     */
    public function changeColumnName($table, $column_name, $new_column_name)
    {
        $sql = sprintf("ALTER TABLE %s RENAME COLUMN %s TO %s", $table, $column_name, $new_column_name);
    
        return $this->runSql($sql);
    }
    
    /**
     * API v2 - Not officialy supported
     * 
     * Change a column type
     * Warning: Not all conversions can be achieved. Check PostreSQL docs on
     * column type changing for more information.
     * 
     * @param unknown $table Table name
     * @param unknown $column_name Column to change
     * @param unknown $new_column_type New data type
     */
    public function changeColumnType($table, $column_name, $new_column_type)
    {
        $sql = sprintf("ALTER TABLE %s ALTER COLUMN %s TYPE %s", $table, $column_name, $new_column_type);
    
        return $this->runSql($sql);
    }

    /**
     * API v2 - Not officialy supported
     * 
     * Gets the name of all available tables
     */
    public function getTableNames()
    {
        $sql = "SELECT \"pg_class\".\"oid\", \"pg_class\".\"relname\" FROM \"pg_class\" INNER JOIN \"pg_namespace\" ON (\"pg_namespace\".\"oid\" = \"pg_class\".\"relnamespace\") WHERE ((\"relkind\" = 'r') AND (\"nspname\" = 'public') AND (\"relname\" NOT IN ('spatial_ref_sys', 'geography_columns', 'geometry_columns', 'raster_columns', 'raster_overviews', 'cdb_tablemetadata')))";
                
        return $this->runSql($sql);
    }

    /**
     * API v2
     * 
     * Inserts data row into $table. 
     * 
     * @param unknown $table Name of table
     * @param unknown $data Array with ($column_name => $new_value) pairs to be updated
     * 
     * @return cartodb_id of inserted row
     */
    public function insertRow($table, $data)
    {
        $keys = implode(',', array_keys($data));
        $values = array();
        foreach(array_values($data) as $key => $elem)
        {
            if(is_null($elem))
                $values[$key] = 'NULL';
            elseif (is_int($elem))
                $values[$key] = sprintf('%d', $elem);
            elseif (is_bool($elem))
                $values[$key] = sprintf('%s', $elem?'1':'0');
            elseif (is_string($elem))
                $values[$key] = sprintf('\'%s\'', pg_escape_string($elem));
            elseif ($elem instanceof \DateTime)
                $values[$key] = sprintf('\'%s\'', $elem->format('Y-m-d\TH:i:sP'));
        }
        $valuesString = implode(',', $values);
        
        $sql = "INSERT INTO $table ($keys) VALUES($valuesString) RETURNING cartodb_id;";
        
        return $this->runSql($sql);
    }

    /**
     * API v2
     * 
     * For $table, updates row with cartodb_id $row_id with $data values
     * 
     * @param unknown $table Table to be updated
     * @param unknown $row_id Cartodb_id of the row to be updated
     * @param unknown $data Array with ($column_name => $new_value) pairs to be updated 
     */
    public function updateRow($table, $row_id, $data)
    {
        $keys = implode(',', array_keys($data));
        $values = array();
        foreach(array_values($data) as $key => $elem)
        {
            if(is_null($elem))
                $values[$key] = 'NULL';
            elseif (is_int($elem))
                $values[$key] = sprintf('%d', $elem);
            elseif (is_bool($elem))
                $values[$key] = sprintf('%s', $elem?'1':'0');
            elseif (is_string($elem))
                $values[$key] = sprintf('\'%s\'', $elem);
            elseif ($elem instanceof \DateTime)
                $values[$key] = sprintf('\'%s\'', $elem->format('Y-m-d\TH:i:sP'));
        }
        $valuesString = implode(',', $values);
        
        $sql = "UPDATE $table SET ($keys) = ($valuesString) WHERE cartodb_id = $row_id RETURNING cartodb_id;";
        
        return $this->runSql($sql);
    }

    /**
     * API v2
     * 
     * Delete row with given id from table
     * 
     * @param unknown $table The name of table
     * @param unknown $row_id Cartobd_id of the row to delete
     */
    public function deleteRow($table, $row_id)
    {
        $sql = "DELETE FROM $table WHERE cartodb_id = $row_id;";
        return $this->runSql($sql);
    }

    /**
     * API v2
     * 
     * Gets all the records of a defined table.
     * @param $table The name of table
     * @param $params array of parameters.
     *   Valid parameters:
     *   - 'rows_per_page' : Number of rows per page.
     *   - 'page' : Page index.
     *   - 'order' : array of $column => asc/desc.
     */
    public function getAllRows($table, $params = array())
    { 
        return $this->getAllRowsForColumns($table, null, $params);
    }

    /**
     * API v2
     * 
     * Gets given columns from all the records of a defined table.
     * @param $table the name of table
     * @param $params array of parameters.
     *   Valid parameters:
     *   - 'rows_per_page' : Number of rows per page.
     *   - 'page' : Page index.
     *   - 'order' : array of $column => asc/desc.
     */
    public function getAllRowsForColumns($table, $columns = null, $params = array())
    {
        return $this->getRowsForColumns($table, $columns, $filter = null, $params);
    }
    
    /**
     * API v2
     * 
     * Gets given columns from the records of a defined table that match the given condition.
     * @param $table the name of table
     * @param $params array of parameters.
     *   Valid parameters:
     *   - 'rows_per_page' : Number of rows per page.
     *   - 'page' : Page index.
     *   - 'order' : array of $column => asc/desc.
     */
    public function getRowsForColumns($table, $columns = null, $filter = null, $params = array())
    {
        if ($columns == null || !is_array($columns) || empty($columns))
            $columnsString = "*";
        else
            $columnsString = implode(', ', $columns);
        
        if ($filter == null || !is_array($filter) || empty($filter))
            $filterString = "1=1";
        else
        {
            $filterString = implode(' AND ', array_map(function($key, $elem)
            {
                if (is_int($elem))
                    return sprintf('%s = %d', $key, $elem);
                if (is_bool($elem))
                    return sprintf('%s = %s', $key, $elem?'1':'0');
                if (is_string($elem))
                    return sprintf('%s = \'%s\'', $key, $elem);
            }, array_keys($filter), $filter));
        }
        
        $extrasString = '';
        if (isset($params['rows_per_page']))
        {
            $extrasString .= sprintf(" LIMIT %s", $params['rows_per_page']);
            if (isset($params['page']))
                $extrasString .= sprintf(" OFFSET %s", $params['page']);
        }
        if (isset($params['order']))
        {
            $extrasString .= 'ORDER BY '.implode(',', array_map(function ($field, $order){
                return sprintf('%s %s', $field, $order);
            }, array_flip($params['order']), $params['order']));
        }
        
        $sql = sprintf("SELECT %s FROM %s WHERE %s %s", $columnsString, $table, $filterString, $extrasString);
        
        return $this->runSql($sql);
    }

    protected function http_parse_headers($header)
    {
        $retVal = array();
        $fields = explode("\r\n",
                preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $header));
        foreach ($fields as $field) {
            if (preg_match('/([^:]+): (.+)/m', $field, $match)) {
                $match[1] = preg_replace('/(?<=^|[\x09\x20\x2D])./e',
                        'strtoupper("\0")', strtolower(trim($match[1])));
                if (isset($retVal[$match[1]])) {
                    $retVal[$match[1]] = array($retVal[$match[1]], $match[2]);
                } else {
                    $retVal[$match[1]] = trim($match[2]);
                }
            }
        }
        return $retVal;
    }

    protected function parse_query($var, $only_params = false)
    {
        /**
         *  Use this function to parse out the query array element from
         *  the output of parse_url().
         */
        if (!$only_params) {
            $var = parse_url($var, PHP_URL_QUERY);
            $var = html_entity_decode($var);
        }

        $var = explode('&', $var);
        $arr = array();

        foreach ($var as $val) {
            $x = explode('=', $val);
            $arr[$x[0]] = $x[1];
        }
        unset($val, $x, $var);
        return $arr;
    }
}

?>