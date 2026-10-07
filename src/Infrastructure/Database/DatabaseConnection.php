<?php
declare(strict_types=1);

final class DatabaseConnection
{
    public static function open(): mysqli
    {
        $connection = new mysqli(
            getenv('FLASHFOOD_DB_HOST') ?: 'localhost',
            getenv('FLASHFOOD_DB_USER') ?: 'root',
            getenv('FLASHFOOD_DB_PASSWORD') ?: '',
            getenv('FLASHFOOD_DB_NAME') ?: 'flashfoodcart'
        );
        if ($connection->connect_errno) throw new RuntimeException('Database connection failed.');
        $connection->set_charset('utf8mb4');
        return $connection;
    }
}
