<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = dirname(__DIR__, 2) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$config = require dirname(__DIR__, 2) . '/config.php';

App\Auth::start($config);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

try {
    App\Router::dispatch(
        App\Db::connect($config['db']),
        $_SERVER['REQUEST_METHOD'] ?? 'GET',
        rtrim($path, '/') ?: '/'
    );
} catch (Throwable $e) {
    error_log((string) $e);
    App\Http::error('server_error', 'Något gick fel.', 500);
}
