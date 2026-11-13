<?php
// Společné funkce pro API (načítá se z content.php a verify.php, přímo se nevolá).

declare(strict_types=1);

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(404);
    exit;
}

const DEFAULT_CONTENT_FILE = __DIR__ . '/../data/content.json';
const STORAGE_DIR          = __DIR__ . '/../storage';
const LIVE_CONTENT_FILE    = STORAGE_DIR . '/content.json';
const BACKUP_DIR           = STORAGE_DIR . '/backups';
const RATE_FILE            = STORAGE_DIR . '/login-attempts.json';
const MAX_BODY_BYTES       = 51200;   // 50 KB
const MAX_FAILED_LOGINS    = 20;      // za okno níže, na jednu IP
const RATE_WINDOW_SECONDS  = 900;     // 15 minut
const BACKUPS_TO_KEEP      = 30;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function json_out(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function admin_password(): string
{
    $file = __DIR__ . '/config.php';
    if (!is_file($file)) {
        return '';
    }
    $config = require $file;
    return is_array($config) ? (string)($config['admin_password'] ?? '') : '';
}

function ensure_storage(): void
{
    foreach ([STORAGE_DIR, BACKUP_DIR] as $dir) {
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            json_out(500, ['error' => 'Nelze vytvořit složku storage na serveru.']);
        }
    }
    $ht = STORAGE_DIR . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
    }
}

function client_ip(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

/** Vrátí počet neúspěšných pokusů této IP v aktuálním okně. */
function failed_attempts(): int
{
    if (!is_file(RATE_FILE)) {
        return 0;
    }
    $data = json_decode((string)@file_get_contents(RATE_FILE), true);
    $entry = is_array($data) ? ($data[client_ip()] ?? null) : null;
    if (!is_array($entry) || (time() - (int)$entry['since']) > RATE_WINDOW_SECONDS) {
        return 0;
    }
    return (int)$entry['count'];
}

function record_failed_attempt(): void
{
    ensure_storage();
    $fh = @fopen(RATE_FILE, 'c+');
    if (!$fh) {
        return;
    }
    flock($fh, LOCK_EX);
    $data = json_decode((string)stream_get_contents($fh), true);
    if (!is_array($data)) {
        $data = [];
    }
    $now = time();
    foreach ($data as $ip => $entry) {   // úklid starých záznamů
        if (!is_array($entry) || ($now - (int)($entry['since'] ?? 0)) > RATE_WINDOW_SECONDS) {
            unset($data[$ip]);
        }
    }
    $ip = client_ip();
    $data[$ip] = [
        'since' => $data[$ip]['since'] ?? $now,
        'count' => ($data[$ip]['count'] ?? 0) + 1,
    ];
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($data));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
}

/** Ověří heslo z hlavičky X-Admin-Password; při chybě rovnou odpoví a skončí. */
function require_admin(): void
{
    $expected = admin_password();
    if ($expected === '') {
        json_out(503, ['error' => 'Heslo admina není nastaveno (chybí api/config.php).']);
    }
    if (failed_attempts() >= MAX_FAILED_LOGINS) {
        json_out(429, ['error' => 'Příliš mnoho pokusů, zkuste to za 15 minut.']);
    }
    $given = (string)($_SERVER['HTTP_X_ADMIN_PASSWORD'] ?? '');
    if (!hash_equals($expected, $given)) {
        record_failed_attempt();
        json_out(401, ['error' => 'Nesprávné heslo.']);
    }
}
