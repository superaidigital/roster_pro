<?php
// ที่อยู่ไฟล์: config/database.php

/**
 * Database connection wrapper.
 *
 * Security change:
 * - production credentials can be supplied with environment variables
 * - local XAMPP defaults are preserved for backward compatibility
 * - PDO native prepares remain enabled
 */
class Database {
    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;

    public $conn;

    public function __construct() {
        // Do not commit production credentials into Git.
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->port = getenv('DB_PORT') ?: '3306';
        $this->db_name = getenv('DB_NAME') ?: 'roster_pro_db';
        $this->username = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASS');
        if ($this->password === false) {
            // Local XAMPP compatibility only. Production should always set DB_PASS.
            $this->password = '';
        }
    }

    public function getConnection() {
        if ($this->conn instanceof PDO) {
            return $this->conn;
        }

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $this->host,
                $this->port,
                $this->db_name
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 5,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            $this->conn->exec("SET time_zone = '+07:00'");

            return $this->conn;
        } catch (PDOException $exception) {
            // Log details server-side only. Never expose DSN/credentials/SQL errors.
            error_log('Database Connection Error: ' . $exception->getMessage());

            http_response_code(500);
            exit(
                "<div style='font-family:sans-serif;padding:20px;text-align:center;color:#dc2626'>" .
                "<h2>⚠️ เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล</h2>" .
                "<p>ไม่สามารถติดต่อฐานข้อมูลของระบบได้ในขณะนี้ กรุณาลองใหม่ภายหลัง</p>" .
                "</div>"
            );
        }
    }
}
