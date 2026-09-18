<?php
namespace App\Handlers;

use App\Http;
use PDO;

final class BootstrapHandler
{
    public function __construct(private PDO $pdo) {}

    public function index(?array $user, ?string $id = null): void
    {
        Http::error('not_implemented', 'Inte implementerad än.', 501);
    }
}
