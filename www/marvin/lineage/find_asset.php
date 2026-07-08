<?php
/**
 * find_asset.php — resolve a script path to its MarvinDB asset id.
 * Identity: (schema=directory, name=file), case-insensitive; basename fallback.
 */
date_default_timezone_set('UTC');
header('Content-Type: application/json');

require_once __DIR__ . '/lineage_store.php';

$path = isset($_GET['path']) ? trim($_GET['path']) : '';
if ($path === '') { echo json_encode(array('error' => 'missing path')); exit; }

try {
    $pdo = lin_marvinPdo();
    $fwd = collapseDots(str_replace('\\', '/', $path));
    list($dir, $file) = lin_splitPath($fwd);

    $stmt = $pdo->prepare(
        'SELECT id FROM Assets
         WHERE schema=? COLLATE NOCASE AND name=? COLLATE NOCASE
           AND category>=200 AND isCurrentValue=1 LIMIT 1'
    );
    $stmt->execute(array($dir, $file));
    $id = $stmt->fetchColumn();

    if ($id === false) {
        $stmt = $pdo->prepare(
            'SELECT id FROM Assets
             WHERE name=? COLLATE NOCASE AND category>=200 AND isCurrentValue=1 LIMIT 1'
        );
        $stmt->execute(array($file));
        $id = $stmt->fetchColumn();
    }

    if ($id !== false) {
        echo json_encode(array('id' => (int)$id));
    } else {
        echo json_encode(array('error' => 'not found', 'path' => $fwd));
    }
} catch (Exception $e) {
    echo json_encode(array('error' => $e->getMessage()));
}
