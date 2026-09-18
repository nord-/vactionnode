<?php
namespace App\Handlers;

use App\Http;
use PDO;

final class SessionHandler
{
    public function __construct(private PDO $pdo) {}

    public function login(?array $user, ?string $id = null): void
    {
        Http::error('not_implemented', 'Inte implementerad än.', 501);
    }

    public function me(?array $user, ?string $id = null): void
    {
        Http::error('not_implemented', 'Inte implementerad än.', 501);
    }

    public function logout(?array $user, ?string $id = null): void
    {
        Http::error('not_implemented', 'Inte implementerad än.', 501);
    }

    public function changePassword(?array $user, ?string $id = null): void
    {
        Http::error('not_implemented', 'Inte implementerad än.', 501);
    }
}
