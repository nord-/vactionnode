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
    // Aldrig (string) $e eller $e->getTraceAsString(): PHP inkluderar
    // anropsargument i spårningen om inte zend.exception_ignore_args=1 är
    // satt, vilket php:8.2-apache-avbildningen inte gör. Db::connect() körs
    // i samma try, så ett fel lösenord eller en onåbar databas hade skrivit
    // PDO->__construct()-anropet — lösenordet inklusive — rakt in i
    // Apache-loggen.
    error_log(sprintf('%s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()));
    App\Http::error('server_error', 'Något gick fel.', 500);
}
