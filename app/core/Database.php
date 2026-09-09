<?php
/**
 * Database Connection Class
 */

class Database
{
    private static $instance = null;
    private $pdo = null;

    /**
     * Get singleton instance
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize PDO connection
     */
    private function __construct()
    {
        try {
            $this->pdo = new PDO(
                DB_DSN,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            die('Database connection failed. Please try again later.');
        }
    }

    /**
     * Call stored procedure
     */
    public function call($procedureName, $params = [])
    {
        try {
            $placeholders = implode(',', array_fill(0, count($params), '?'));
            $sql = "CALL $procedureName($placeholders)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log('Database error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Close any remaining MySQL result set from a stored procedure
     */
    public function closeProcedureCursor($stmt)
    {
        if ($stmt instanceof PDOStatement) {
            $stmt->closeCursor();
        }
    }

    /**
     * Execute query with parameters
     */
    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log('Database error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get PDO instance for advanced operations
     */
    public function getPDO()
    {
        return $this->pdo;
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    /**
     * Prevent cloning
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserializing
     */
    public function __wakeup()
    {
    }
}
