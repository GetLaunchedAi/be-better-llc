<?php
// /public_html/api/save-products.php
declare(strict_types=1);

// Allow only POST from same origin
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  header('Allow: POST');
  echo 'Method Not Allowed';
  exit;
}

// Simple auth using server env token
$provided = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
$expected = getenv('ADMIN_TOKEN'); // from .htaccess SetEnv
if (!$expected || !hash_equals($expected, $provided)) {
  http_response_code(401);
  echo 'Unauthorized';
  exit;
}

// File paths
$root = dirname(__DIR__, 1); // /public_html
$dataFile = $root . '/products.json';
$backupDir = $root . '/backups';

// Read current file + etag
$current = file_exists($dataFile) ? file_get_contents($dataFile) : '[]';
$currentEtag = '"' . sha1($current) . '"';

// Debug logging (remove in production)
error_log("Current ETag: " . $currentEtag);
error_log("If-Match header: " . ($_SERVER['HTTP_IF_MATCH'] ?? 'not set'));

// If-Match (optimistic concurrency)
$ifMatch = $_SERVER['HTTP_IF_MATCH'] ?? '';
if ($ifMatch && trim($ifMatch) !== $currentEtag) {
  http_response_code(412); // Precondition Failed
  echo 'Precondition Failed';
  exit;
}

// Read posted JSON
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

// Validate JSON structure - handle both flat array and nested object with products array
$products = null;
if (is_array($data)) {
  // Check if it's a flat array of products
  if (count($data) > 0 && isset($data[0]['id'])) {
    $products = $data;
  }
  // Check if it's a nested object with products array
  else if (isset($data['products']) && is_array($data['products'])) {
    $products = $data['products'];
  }
}

if (!$products) {
  http_response_code(400);
  echo 'Payload must be a JSON array of products or an object with a products array';
  exit;
}

$ids = [];
foreach ($products as $i => $p) {
  if (!is_array($p) || !isset($p['id']) || trim((string)$p['id']) === '') {
    http_response_code(400);
    echo "Item $i missing non-empty 'id'";
    exit;
  }
  if (isset($ids[$p['id']])) {
    http_response_code(400);
    echo "Duplicate id: {$p['id']}";
    exit;
  }
  $ids[$p['id']] = true;
}

// Ensure backup dir exists
if (!is_dir($backupDir)) {
  @mkdir($backupDir, 0755, true);
}

// Backup current file
$ts = date('Ymd-His');
@file_put_contents("$backupDir/products-$ts.json", $current);

// Atomic write: temp file then rename
$tmp = $dataFile . '.tmp';

// Preserve original structure - if data was nested, keep it nested
$writeData = $data;
if (isset($data['products'])) {
  // Update the products array in the original structure
  $writeData['products'] = $products;
}

// Ensure consistent JSON formatting to match what the admin page expects
$newJson = json_encode($writeData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
// Add exactly one newline at the end to match the original format
if (!str_ends_with($newJson, "\n")) {
  $newJson .= "\n";
}
if (file_put_contents($tmp, $newJson, LOCK_EX) === false) {
  http_response_code(500);
  echo 'Failed to write temp file';
  exit;
}
if (!@rename($tmp, $dataFile)) {
  // Fallback if rename fails on some filesystems
  if (file_put_contents($dataFile, $newJson, LOCK_EX) === false) {
    http_response_code(500);
    echo 'Failed to save file';
    exit;
  }
}

// Respond with new ETag
$newEtag = '"' . sha1($newJson) . '"';
header('Content-Type: application/json');
echo json_encode(['ok' => true, 'etag' => $newEtag]);
