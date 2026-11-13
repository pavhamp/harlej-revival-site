<?php
// GET  → obsah webu (uložený z adminu, jinak výchozí data/content.json)
// POST → uložení obsahu z admin panelu (vyžaduje hlavičku X-Admin-Password)
require __DIR__ . '/_lib.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $file = is_file(LIVE_CONTENT_FILE) ? LIVE_CONTENT_FILE : DEFAULT_CONTENT_FILE;
    $raw = @file_get_contents($file);
    if ($raw === false) {
        json_out(404, ['error' => 'Obsah nenalezen.']);
    }
    echo $raw;
    exit;
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    json_out(405, ['error' => 'Nepodporovaná metoda.']);
}

require_admin();

$body = file_get_contents('php://input', false, null, 0, MAX_BODY_BYTES + 1);
if ($body === false || strlen($body) > MAX_BODY_BYTES) {
    json_out(413, ['error' => 'Data jsou příliš velká.']);
}
$data = json_decode($body, true);
if (!is_array($data) || $data === [] || array_keys($data) === range(0, count($data) - 1)) {
    json_out(400, ['error' => 'Neplatný formát dat.']);
}

ensure_storage();

// záloha předchozí verze
if (is_file(LIVE_CONTENT_FILE)) {
    @copy(LIVE_CONTENT_FILE, BACKUP_DIR . '/content-' . date('Ymd-His') . '.json');
    $backups = glob(BACKUP_DIR . '/content-*.json') ?: [];
    sort($backups);
    foreach (array_slice($backups, 0, max(0, count($backups) - BACKUPS_TO_KEEP)) as $old) {
        @unlink($old);
    }
}

$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$tmp = LIVE_CONTENT_FILE . '.tmp';
if ($json === false || @file_put_contents($tmp, $json, LOCK_EX) === false || !@rename($tmp, LIVE_CONTENT_FILE)) {
    @unlink($tmp);
    json_out(500, ['error' => 'Uložení se nezdařilo (práva k zápisu na serveru?).']);
}

json_out(200, ['ok' => true]);
