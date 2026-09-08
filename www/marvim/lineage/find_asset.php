<?php
/**
 * find_asset.php — resolve a path to its MarvimDB asset id + category, so the
 * caller can route a workflow (category >= 200) to oneWorkflow.php and a file /
 * table to table.php. Identity: (schema=directory, name=file), case-insensitive;
 * trailing-slash tolerant; basename fallback. isCurrentValue-agnostic (prefers
 * current rows) so legacy / imported rows still resolve.
 */
date_default_timezone_set('UTC');
header('Content-Type: application/json');

require_once __DIR__ . '/lineage_store.php';

$path = isset($_GET['path']) ? trim($_GET['path']) : '';
if ($path === '') { echo json_encode(array('error' => 'missing path')); exit; }

try {
    $pdo = lin_marvimPdo();
    $fwd = collapseDots(str_replace('\\', '/', $path));
    list($dir, $file) = lin_splitPath($fwd);

    $tryQueries = array(
        array('SELECT id, category FROM Assets
               WHERE (schema=? OR schema=? || \'/\') COLLATE NOCASE AND name=? COLLATE NOCASE
               ORDER BY isCurrentValue DESC, category >= 200 DESC LIMIT 1', array($dir, $dir, $file)),
        array('SELECT id, category FROM Assets
               WHERE name=? COLLATE NOCASE
               ORDER BY isCurrentValue DESC, category >= 200 DESC LIMIT 1', array($file)),
    );
    $row = false;
    foreach ($tryQueries as $q) {
        $stmt = $pdo->prepare($q[0]);
        $stmt->execute($q[1]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) break;
    }

    if ($row) {
        echo json_encode(array('id' => (int)$row['id'], 'category' => (int)$row['category']));
    } else {
        echo json_encode(array('error' => 'not found', 'path' => $fwd));
    }
} catch (Exception $e) {
    echo json_encode(array('error' => $e->getMessage()));
}
