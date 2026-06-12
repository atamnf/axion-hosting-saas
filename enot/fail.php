<?php
require_once __DIR__ . '/../config.php';

$to = rtrim(SITE_URL, '/') . '/dashboard.php?payment=fail';
header('Location: ' . $to, true, 302);
exit;
