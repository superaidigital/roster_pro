<?php
// ที่อยู่ไฟล์: config/database.php
// ชื่อไฟล์: database.php

class Database {
    private string $host;
    private string $port;
    private string $db_name;
    private string $username;
    private string $password;
    private string $timezone;
    public ?PDO $conn = null;

    public function __construct() {
        // ค่าเริ่มต้นรองรับ XAMPP localhost โดยไม่ต้องสร้าง .env
        // Production สามารถ override ผ่าน Environment Variables ได้
        $this->host = getenv('DB_HOST') ?: '127.0.0.1';
        $this->port = getenv('DB_PORT') ?: '3306';
        $this->db_name = getenv('DB_NAME') ?: 'roster_pro_db';
        $this->username = getenv('DB_USER') ?: 'root';

        $envPassword = getenv('DB_PASSWORD');
        $this->password = ($envPassword !== false) ? $envPassword : '';

        $this->timezone = getenv('DB_TIMEZONE') ?: '+07:00';
    }

    public function getConnectionOrThrow(): PDO {
        if ($this->conn instanceof PDO) {
            return $this->conn;
        }

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
        ];

        $this->conn = new PDO($dsn, $this->username, $this->password, $options);

        if (!preg_match('/^[+-](?:0\d|1\d|2[0-3]):[0-5]\d$/', $this->timezone)) {
            $this->timezone = '+07:00';
        }

        $quotedTimezone = $this->conn->quote($this->timezone);
        $this->conn->exec("SET time_zone = {$quotedTimezone}");
        $this->conn->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

        return $this->conn;
    }

    public function getConnection(): PDO {
        try {
            return $this->getConnectionOrThrow();
        } catch (PDOException $exception) {
            error_log(
                sprintf(
                    'Database Connection Error [%s:%s/%s]: %s',
                    $this->host,
                    $this->port,
                    $this->db_name,
                    $exception->getMessage()
                )
            );

            http_response_code(500);
            die("<div style='font-family:Tahoma,Arial,sans-serif;padding:40px;text-align:center;color:#dc2626;'>
                    <h2>⚠️ เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล</h2>
                    <p>ไม่สามารถติดต่อฐานข้อมูลของระบบ Roster Pro ได้ในขณะนี้</p>
                    <p style='color:#64748b;font-size:14px;'>กรุณาตรวจสอบการตั้งค่าฐานข้อมูลและสถานะ MariaDB/MySQL</p>
                 </div>");
        }
    }

}
