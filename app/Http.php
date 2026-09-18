<?php
namespace App;

final class Http
{
    // use App\HttpException; behövs inte — samma namnrymd.
    private static ?string $testBody = null;
    private static int $lastStatus = 200;

    public static function setTestBody(?string $body): void
    {
        self::$testBody = $body;
    }

    public static function lastStatus(): int
    {
        return self::$lastStatus;
    }

    public static function json(mixed $data, int $status = 200): void
    {
        self::$lastStatus = $status;
        // http_response_code() kräver riktiga headers. Under PHPUnit körs SAPI
        // "cli", där anropet bara hade kastat en varning — hoppa över det då
        // och låt testriggen läsa statusen via lastStatus() i stället.
        if (PHP_SAPI !== 'cli') {
            http_response_code($status);
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function error(string $code, string $message, int $status): void
    {
        self::json(['error' => ['code' => $code, 'message' => $message]], $status);
    }

    /**
     * Läser och avkodar request-body:n. Kravet på application/json är en del
     * av CSRF-skyddet: ett formulär från en annan sajt kan inte sätta headern
     * utan preflight, och preflighten faller på CORS.
     */
    public static function body(): array
    {
        $type = $_SERVER['CONTENT_TYPE'] ?? '';
        if (!str_starts_with($type, 'application/json')) {
            // Kastar, aldrig exit: front controllern körs i samma process som
            // PHPUnit, och ett exit hade dödat hela testsviten vid första
            // felsvaret i stället för att låta testet göra sin assertion.
            throw new HttpException('unsupported_media_type', 'Kräver application/json.', 415);
        }

        $decoded = json_decode(self::$testBody ?? (file_get_contents('php://input') ?: ''), true);

        return is_array($decoded) ? $decoded : [];
    }
}
