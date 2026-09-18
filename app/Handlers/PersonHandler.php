<?php
namespace App\Handlers;

use App\Http;
use PDO;

final class PersonHandler
{
    public function __construct(private PDO $pdo) {}

    public function create(?array $user, ?string $id = null): void
    {
        Http::error('not_implemented', 'Inte implementerad än.', 501);
    }

    public function update(?array $user, ?string $id = null): void
    {
        Http::error('not_implemented', 'Inte implementerad än.', 501);
    }

    public function destroy(?array $user, ?string $id = null): void
    {
        Http::error('not_implemented', 'Inte implementerad än.', 501);
    }
}
