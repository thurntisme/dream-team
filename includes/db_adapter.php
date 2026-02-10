<?php
class DBAdapter
{
    private $driver;
    private $sqlite;
    private $pdo;
    private $lastError;

    public function __construct($driver, $config)
    {
        $this->driver = $driver;
        $this->lastError = '';
        if ($driver === 'sqlite') {
            $this->sqlite = new SQLite3($config['db_file']);
            $this->sqlite->exec('PRAGMA foreign_keys = ON');
        } else {
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
        if ($this->driver === 'sqlite') {
            $stmt = $this->sqlite->prepare($sql);
            if (!$stmt) {
                $this->lastError = $this->sqlite->lastErrorMsg();
            }
            return new DBStatement($this->driver, $stmt, null, $this);
        }
        try {
            $stmt = $this->pdo->prepare($sql);
            return new DBStatement($this->driver, null, $stmt, $this);
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function query($sql)
    {
        if ($this->driver === 'sqlite') {
            $res = $this->sqlite->query($sql);
            if (!$res) {
                $this->lastError = $this->sqlite->lastErrorMsg();
                return false;
            }
            return new DBResult($this->driver, $res, null);
        }
        try {
            $stmt = $this->pdo->query($sql);
            return new DBResult($this->driver, null, $stmt);
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function exec($sql)
    {
        if ($this->driver === 'sqlite') {
            $ok = $this->sqlite->exec($sql);
            if (!$ok) {
                $this->lastError = $this->sqlite->lastErrorMsg();
            }
            return $ok;
        }
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
        if ($this->driver === 'sqlite') {
            return $this->sqlite->lastErrorMsg();
        }
        return $this->lastError;
    }

    public function lastInsertRowID()
    {
        if ($this->driver === 'sqlite') {
            return $this->sqlite->lastInsertRowID();
        }
        return $this->pdo ? $this->pdo->lastInsertId() : null;
    }


    public function close()
    {
        if ($this->driver === 'sqlite') {
            if ($this->sqlite) {
                $this->sqlite->close();
            }
        } else {
            $this->pdo = null;
        }
    }
}

class DBStatement
{
    private $driver;
    private $sqliteStmt;
    private $pdoStmt;
    private $adapter;
    private $bound = [];

    public function __construct($driver, $sqliteStmt, $pdoStmt, $adapter)
    {
        $this->driver = $driver;
        $this->sqliteStmt = $sqliteStmt;
        $this->pdoStmt = $pdoStmt;
        $this->adapter = $adapter;
    }

    public function bindValue($param, $value, $type = null)
    {
        if ($this->driver === 'sqlite') {
            return $this->sqliteStmt->bindValue($param, $value, $type);
        }
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
        if ($this->driver === 'sqlite') {
            $res = $this->sqliteStmt->execute();
            if (!$res) {
                return false;
            }
            return new DBResult($this->driver, $res, null);
        }
        try {
            $ok = $this->pdoStmt->execute();
            if (!$ok) {
                return false;
            }
            return new DBResult($this->driver, null, $this->pdoStmt);
        } catch (Throwable $e) {
            return false;
        }
    }
}

class DBResult
{
    private $driver;
    private $sqliteRes;
    private $pdoStmt;

    public function __construct($driver, $sqliteRes, $pdoStmt)
    {
        $this->driver = $driver;
        $this->sqliteRes = $sqliteRes;
        $this->pdoStmt = $pdoStmt;
    }

    public function fetchArray($mode = SQLITE3_ASSOC)
    {
        if ($this->driver === 'sqlite') {
            return $this->sqliteRes->fetchArray($mode);
        }
        return $this->pdoStmt->fetch(PDO::FETCH_ASSOC);
    }
}
