<?php
/**
 * Indus Grammar School ERP - Database Service
 * Version 1.0.0
 */

class Database {
    private static ?PDO $instance = null;

    /**
     * Get the database connection instance (Singleton)
     *
     * @return PDO
     * @throws PDOException
     */
    public static function getConnection(): PDO {
        if (self::$instance === null) {
            try {
                $dsn = sprintf(
                    "mysql:host=%s;port=%s;dbname=%s;charset=%s",
                    DB_HOST,
                    DB_PORT,
                    DB_NAME,
                    DB_CHARSET
                );

                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
                ];

                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Log error or display message
                error_log("Database connection failed: " . $e->getMessage());
                if (APP_ENV === 'development') {
                    throw new PDOException("Database connection failed: " . $e->getMessage(), (int)$e->getCode());
                } else {
                    die("A database connection error occurred. Please contact the administrator.");
                }
            }
        }

        return self::$instance;
    }

    /**
     * Helper to run queries directly with parameters
     *
     * @param string $sql
     * @param array $params
     * @return PDOStatement
     */
    public static function query(string $sql, array $params = []): PDOStatement {
        $db = self::getConnection();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
