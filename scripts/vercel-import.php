<?php

declare(strict_types=1);
use Pdo\Mysql;

/*
 * Loads a data-only mysqldump into the hosted review database, then replaces
 * every admin password with a random one. Run via `./scripts/vercel db`, which
 * supplies the DB_* variables.
 *
 * PHP rather than the mysql client: XAMPP's MariaDB 10.4 client cannot speak
 * MySQL 8's default caching_sha2_password authentication.
 */

$dump = $argv[1] ?? exit("usage: php scripts/vercel-import.php dump.sql\n");

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST'), getenv('DB_PORT'), getenv('DB_DATABASE')),
    getenv('DB_USERNAME'),
    getenv('DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, Mysql::ATTR_SSL_CA => getenv('MYSQL_ATTR_SSL_CA')],
);

$pdo->exec('SET FOREIGN_KEY_CHECKS=0');

// Re-runnable: empty every content table first. migrations stays — it is what
// `migrate` just wrote.
$skip = ['migrations'];
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
    if (! in_array($table, $skip, true)) {
        $pdo->exec("TRUNCATE TABLE `{$table}`");
    }
}

// mysqldump writes one statement per line ending in ";" and escapes newlines
// inside values, so a line ending ";" is always a statement boundary.
$statement = '';
$count = 0;
foreach (new SplFileObject($dump) as $line) {
    $line = rtrim((string) $line, "\r\n");
    if ($statement === '' && ($line === '' || str_starts_with($line, '--') || preg_match('#^/\*.*\*/;$#', $line))) {
        continue;
    }
    $statement .= $line."\n";
    if (str_ends_with($line, ';')) {
        $pdo->exec($statement);
        $statement = '';
        $count++;
    }
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
echo "  {$count} statements imported\n";

// The seeded accounts all use "password". A public link must not.
$lines = ['CoreMemory review copy — admin logins ('.date('Y-m-d').')', 'Keep private. Share only with your partner.', ''];
foreach ($pdo->query('SELECT id, email FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $user) {
    $password = implode('-', str_split(bin2hex(random_bytes(9)), 6));
    $pdo->prepare('UPDATE users SET password = ?, remember_token = NULL WHERE id = ?')
        ->execute([password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), $user['id']]);
    $lines[] = sprintf('%-28s %s', $user['email'], $password);
}

$file = getenv('HOME').'/corememory-review-credentials.txt';
file_put_contents($file, implode("\n", $lines)."\n");
chmod($file, 0600);
echo "  admin passwords replaced → {$file}\n";
