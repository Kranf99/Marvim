<?php
/**
 * assets_api.php — RETIRED (2026-07). Asset registration now happens
 * automatically inside extract_api.php?action=save_script; the extractor
 * writes directly into MarvinDB (see lineage_store.php / REFACTOR_PLAN.md).
 * Kept as a stub so old bookmarks/callers fail gracefully.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'get_marvin_servers') {
    // still answered, for backward compatibility (also available on both APIs)
    require_once __DIR__ . '/lineage_store.php';
    try {
        $pdo = lin_marvinPdo();
        $rows = $pdo->query(
            "SELECT id, name, serverType FROM servers WHERE isCurrentValue=1 AND name IS NOT NULL AND name != '' ORDER BY name"
        )->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array('error' => $e->getMessage()));
    }
    exit;
}

if ($action === 'register_assets') {
    echo json_encode(array(
        'ok' => true,
        'note' => 'Deprecated: assets are registered automatically during extraction (save_script).',
        'inserted_anatella' => 0, 'skipped_anatella' => 0,
        'inserted_data' => 0, 'skipped_data' => 0,
    ));
    exit;
}

http_response_code(400);
echo json_encode(array('error' => 'Unknown action: ' . $action));
