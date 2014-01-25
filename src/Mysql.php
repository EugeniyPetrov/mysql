<?php

namespace EugeniyPetrov;

class Mysql
{
    protected $_pdo;
    protected $_host;
    protected $_user;
    protected $_password;
    protected $_database;
    protected $_charset;
    protected $_debug;

    public function __construct($host, $user, $password, $database, $charset, $params = null)
    {
        if (!isset($params)) {
            $params = array(
                'debug' => false,
            );
        }
        $this->_host = $host;
        $this->_user = $user;
        $this->_password = $password;
        $this->_database = $database;
        $this->_charset = $charset;
        $this->_debug = $params['debug'];
    }

    protected function _log($message)
    {
        if ($this->_debug) {
            echo '[' . date('Y-m-d H:i:s') . '][' . $message . ']' . PHP_EOL;
        }
    }

    protected function _getPDOInstance()
    {
        if (!isset($this->_pdo)) {
            $this->_log('Connecting...');
            $this->_pdo = new \PDO('mysql:host=' . $this->_host . ';dbname=' . $this->_database, $this->_user, $this->_password, array(
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ));
            $this->query('SET NAMES :charset', array(
                'charset' => $this->_charset,
            ));
        }
        return $this->_pdo;
    }

    public function query($sql, $params = null)
    {
        $sql = $this->formatSql($sql, $params);

        $start = microtime(true);
        $result = $this->_getPDOInstance()->query($sql);
        $exec_time = microtime(true) - $start;
        $this->_log($sql . ' - ' . round($exec_time, 4) . ' sec');

        if ($result === false) {
            $error_info = $this->_getPDOInstance()->errorInfo();
            throw new Mysql\Exception($error_info[2]);
        }

        return $result;
    }

    public function unbuffered_query($sql, $params = null)
    {
        $this->_getPDOInstance()->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $result = $this->query($sql, $params);
        $this->_getPDOInstance()->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

        return $result;
    }

    public function all($sql, $params = null, $options = null)
    {
        $mc = null;
        if (isset($options['cache_key'])) {
            $mc = memcached($this->_database);
            $value = $mc->get('mysql.' . $options['cache_key']);
            if ($mc->getResultCode() == Memcached::RES_SUCCESS) {
                $this->_log('Cached (' . $options['cache_key'] . ')');
                return $value;
            }
        }
        $result = $this->query($sql, $params)->fetchAll();

        if (isset($mc)) {
            $mc->set('mysql.' . $options['cache_key'], $result, 3600 * 24);
        }

        return $result;
    }

    public function one($sql, $params = null, $options = null)
    {
        $rows = $this->all($sql, $params, $options);
        if (!$rows) return null;
        return array_shift($rows[0]);
    }

    public function col($sql, $params = null, $options = null)
    {
        $rows = $this->all($sql, $params, $options);
        if (!$rows) return array();
        $col = array();
        foreach ($rows as $row) {
            $col[] = array_shift($row);
        }
        return $col;
    }

    public function row($sql, $params = null, $options = null)
    {
        $rows = $this->all($sql, $params, $options);
        if (!$rows) return null;
        return $rows[0];
    }

    public function assoc($sql, $params = null, $options = null)
    {
        $rows = $this->all($sql, $params, $options);
        if (!$rows) return array();
        $assoc = array();
        foreach ($rows as $row) {
            $assoc[array_shift($row)] = $row;
        }
        return $assoc;
    }

    public function inserted_id()
    {
        return $this->one('SELECT LAST_INSERT_ID()');
    }

    public function formatSql($sql, $params = null)
    {
        $keys = array();
        if ($params) {
            krsort($params);
            foreach ($params as $key => $value) {
                $keys[] = ':' . preg_quote($key);
            }
        }
        $tokens = array($sql);
        if ($keys) {
            $tokens = preg_split('~(' . join('|', $keys) . ')~', $sql, null, PREG_SPLIT_DELIM_CAPTURE);
        }
        $sql = '';
        foreach ($tokens as $token) {
            if (substr($token, 0, 1) == ':') {
                $key = substr($token, 1);
                if (array_key_exists($key, $params)) {
                    $token = $this->quote($params[$key]);
                }
            }
            $sql .= $token;
        }
        return $sql;
    }

    public function quote($var)
    {
        $type = gettype($var);
        if ($type == 'integer') return intval($var);
        elseif ($type == 'double') return doubleval($var);
        elseif ($type == 'boolean') return $var ? 'TRUE' : 'FALSE';
        elseif ($type == 'NULL') return 'NULL';
        elseif ($type == 'string') return $this->_getPDOInstance()->quote($var);
        elseif ($type == 'array') return '(' . join(', ', array_map(array($this, 'quote'), $var)) . ')';
        else new InvalidArgumentException("Invalid type of var - $type");
    }

    public function flushCache($cache_key)
    {
        $mc = memcached($this->_database);
        $value = $mc->delete('mysql.' . $cache_key);
    }
}
