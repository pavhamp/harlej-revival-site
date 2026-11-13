<?php
// Ověření hesla do admin panelu.
require __DIR__ . '/_lib.php';

require_admin();
json_out(200, ['ok' => true]);
