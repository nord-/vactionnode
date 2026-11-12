<?php
declare(strict_types=1);

require __DIR__ . '/../app/Db.php';
require __DIR__ . '/../app/Uuid.php';
require __DIR__ . '/../app/Persons.php';

$config = require __DIR__ . '/../config.php';
$pdo    = App\Db::connect($config['db']);

fwrite(STDOUT, 'Namn: ');
$name = trim((string) fgets(STDIN));
fwrite(STDOUT, 'E-post: ');
$email = trim((string) fgets(STDIN));
fwrite(STDOUT, 'Lösenord: ');
$password = trim((string) fgets(STDIN));

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
    fwrite(STDERR, "Namn, giltig e-post och lösenord på minst 8 tecken krävs.\n");
    exit(1);
}

try {
    $person = App\Persons::create($pdo, $name, $email, $password, null, true);
} catch (PDOException $e) {
    // 23000 = integritetsvillkorskränkning. groupId är null här, så den enda
    // rimliga orsaken är att e-postadressen redan finns i users.email.
    if ($e->getCode() === '23000') {
        fwrite(STDERR, "E-postadressen används redan.\n");
        exit(1);
    }
    throw $e;
}

fwrite(STDOUT, "Administratör skapad: {$person['email']} ({$person['id']})\n");
