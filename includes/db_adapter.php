<?php
class DBAdapter
{
    private $driver;
    private $pdo;
    private $lastError;

    public function __construct($driver, $config)
    {
        $this->driver = 'mysql';
        $this->lastError = '';
        $dsn = 'mysql:host=' . ($config['mysql_host'] ?? '127.0.0.1') .
               ';port=' . ($config['mysql_port'] ?? '3306') .
               ';dbname=' . ($config['mysql_db'] ?? '') .
               ';charset=utf8mb4';
        $user = $config['mysql_user'] ?? '';
        $pass = $config['mysql_password'] ?? '';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            throw $e;
        }
        if (!defined('SQLITE3_ASSOC')) {
            define('SQLITE3_ASSOC', 1);
        }
        if (!defined('SQLITE3_INTEGER')) {
            define('SQLITE3_INTEGER', 2);
        }
        if (!defined('SQLITE3_TEXT')) {
            define('SQLITE3_TEXT', 3);
        }
        if (!defined('SQLITE3_FLOAT')) {
            define('SQLITE3_FLOAT', 4);
        }
    }

    public function prepare($sql)
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            if ($stmt === false) {
                $this->lastError = 'PDO::prepare returned false';
                return false;
            }
            return new DBStatement($this->driver, $stmt, $this);
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function query($sql)
    {
        try {
            $stmt = $this->pdo->query($sql);
            return new DBResult($this->driver, $stmt);
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function exec($sql)
    {
        try {
            $rows = $this->pdo->exec($sql);
            return $rows !== false;
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function lastErrorMsg()
    {
        return $this->lastError;
    }

    public function lastInsertRowID()
    {
        return $this->pdo ? $this->pdo->lastInsertId() : null;
    }


    public function close()
    {
        $this->pdo = null;
    }
}

class DBStatement
{
    private $driver;
    private $pdoStmt;
    private $adapter;
    private $bound = [];

    public function __construct($driver, $pdoStmt, $adapter)
    {
        $this->driver = $driver;
        $this->pdoStmt = $pdoStmt;
        $this->adapter = $adapter;
    }

    public function bindValue($param, $value, $type = null)
    {
        $pdoType = PDO::PARAM_STR;
        if ($type === SQLITE3_INTEGER) {
            $pdoType = PDO::PARAM_INT;
        } elseif ($type === SQLITE3_TEXT || $type === SQLITE3_FLOAT || $type === null) {
            $pdoType = PDO::PARAM_STR;
        }
        if (is_int($param)) {
            return $this->pdoStmt->bindValue($param, $value, $pdoType);
        }
        return $this->pdoStmt->bindValue($param, $value, $pdoType);
    }

    public function execute()
    {
        try {
            $ok = $this->pdoStmt->execute();
            if (!$ok) {
                return false;
            }
            return new DBResult($this->driver, $this->pdoStmt);
        } catch (Throwable $e) {
            return false;
        }
    }
}

class DBResult
{
    private $driver;
    private $pdoStmt;

    public function __construct($driver, $pdoStmt)
    {
        $this->driver = $driver;
        $this->pdoStmt = $pdoStmt;
    }

    public function fetchArray($mode = SQLITE3_ASSOC)
    {
        return $this->pdoStmt->fetch(PDO::FETCH_ASSOC);
    }
}
