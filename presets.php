<?php
/**
 * wavy-rings preset storage (folder-based)
 *
 * GET  presets.php              -> ["name1","name2", ...]  (list all)
 * GET  presets.php?name=X       -> { config JSON }
 * POST presets.php              -> save preset (name, config, svg in body)
 * DELETE presets.php?name=X     -> delete preset
 *
 * Presets live in ./presets/ as NAME.json + NAME.svg
 * Reading is public; writing/deleting requires X-Auth-Token header.
 */

declare(strict_types=1);

$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Server not configured: config.php is missing.']);
    exit;
}
$config = require $configPath;

$token         = $config['token'] ?? '';
$presetsDir    = $config['presetsDir'] ?? (__DIR__ . '/presets');
$allowedOrigin = $config['allowedOrigin'] ?? '*';

// CORS
header('Access-Control-Allow-Origin: ' . $allowedOrigin);
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if (!is_dir($presetsDir)) {
    mkdir($presetsDir, 0755, true);
}

function requireAuth(string $expected): void {
    $provided = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    if ($expected === '' || !hash_equals($expected, $provided)) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized.']);
        exit;
    }
}

function safeName(string $name): string {
    $name = trim($name);
    $name = preg_replace('/[^a-zA-Z0-9_\-() äöüÄÖÜß]/u', '', $name);
    $name = preg_replace('/\.{2,}/', '', $name);
    return substr($name, 0, 120);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $name = $_GET['name'] ?? '';
        if ($name !== '') {
            $safe = safeName($name);
            $jsonFile = $presetsDir . '/' . $safe . '.json';
            if (!file_exists($jsonFile)) {
                http_response_code(404);
                echo json_encode(['error' => 'Preset not found.']);
                exit;
            }
            echo file_get_contents($jsonFile);
            exit;
        }
        // List all presets
        $presets = [];
        foreach (glob($presetsDir . '/*.json') as $file) {
            $presets[] = basename($file, '.json');
        }
        sort($presets, SORT_NATURAL | SORT_FLAG_CASE);
        echo json_encode($presets);
        exit;
    }

    if ($method === 'POST') {
        requireAuth($token);

        $raw = file_get_contents('php://input');
        if (strlen($raw) > 2_000_000) {
            http_response_code(413);
            echo json_encode(['error' => 'Payload too large.']);
            exit;
        }

        $data = json_decode($raw, true);
        if (!$data || !isset($data['name'], $data['config'], $data['svg'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Body must contain name, config, and svg.']);
            exit;
        }

        $safe = safeName($data['name']);
        if ($safe === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid preset name.']);
            exit;
        }

        $configJson = json_encode(
            $data['config'],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        file_put_contents($presetsDir . '/' . $safe . '.json', $configJson, LOCK_EX);
        file_put_contents($presetsDir . '/' . $safe . '.svg', $data['svg'], LOCK_EX);

        echo json_encode(['ok' => true, 'name' => $safe]);
        exit;
    }

    if ($method === 'DELETE') {
        requireAuth($token);

        $name = $_GET['name'] ?? '';
        $safe = safeName($name);
        if ($safe === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Missing or invalid name.']);
            exit;
        }

        $deleted = false;
        foreach ([$safe . '.json', $safe . '.svg'] as $f) {
            $path = $presetsDir . '/' . $f;
            if (file_exists($path)) {
                unlink($path);
                $deleted = true;
            }
        }

        if (!$deleted) {
            http_response_code(404);
            echo json_encode(['error' => 'Preset not found.']);
            exit;
        }

        echo json_encode(['ok' => true]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
