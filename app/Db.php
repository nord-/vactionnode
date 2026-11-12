<?php
namespace App;

use PDO;

final class Db
{
    public static function connect(array $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['host'], $config['port'], $config['name']
        );

        // MYSQL_ATTR_FOUND_ROWS MÅSTE ligga här, i konstruktorns options.
        // Ett setAttribute() efter att anslutningen öppnats ignoreras tyst,
        // och rowCount() räknar då ändrade rader i stället för matchade.
        return new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_FOUND_ROWS   => true,
        ]);
    }
}
