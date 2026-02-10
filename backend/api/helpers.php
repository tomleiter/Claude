<?php

function corsHeaders(): void {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function jsonResponse(mixed $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function jsonError(string $message, int $code = 400): void {
    jsonResponse(['error' => $message], $code);
}

function getJsonBody(): array {
    $body = file_get_contents('php://input');
    return json_decode($body, true) ?? [];
}

function generateId(): string {
    return bin2hex(random_bytes(8));
}

function generateShareToken(): string {
    return bin2hex(random_bytes(16));
}

function createJwt(array $payload): string {
    $header = base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
    $payload['exp'] = time() + 86400 * 7; // 7 days
    $payloadEncoded = base64url_encode(json_encode($payload));
    $signature = base64url_encode(hash_hmac('sha256', "$header.$payloadEncoded", JWT_SECRET, true));
    return "$header.$payloadEncoded.$signature";
}

function verifyJwt(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$header, $payload, $signature] = $parts;
    $expectedSig = base64url_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    if (!hash_equals($expectedSig, $signature)) return null;
    $data = json_decode(base64url_decode($payload), true);
    if (!$data || ($data['exp'] ?? 0) < time()) return null;
    return $data;
}

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/'));
}

function requireAuth(): array {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
        jsonError('Unauthorized', 401);
    }
    $payload = verifyJwt($matches[1]);
    if (!$payload) jsonError('Invalid token', 401);
    return $payload;
}

function loadFolderTree(): array {
    $file = FOLDERS_PATH . '/tree.json';
    if (!file_exists($file)) {
        $default = ['id' => 'root', 'name' => 'Root', 'children' => [], 'shareTokens' => []];
        file_put_contents($file, json_encode($default, JSON_PRETTY_PRINT));
        return $default;
    }
    return json_decode(file_get_contents($file), true);
}

function saveFolderTree(array $tree): void {
    file_put_contents(FOLDERS_PATH . '/tree.json', json_encode($tree, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function loadFolderData(string $folderId): array {
    $file = FOLDERS_PATH . "/{$folderId}.json";
    if (!file_exists($file)) {
        return [
            'id' => $folderId,
            'canvasImage' => null,
            'fields' => [],
            'data' => [],
            'released' => false,
        ];
    }
    return json_decode(file_get_contents($file), true);
}

function saveFolderData(string $folderId, array $data): void {
    file_put_contents(FOLDERS_PATH . "/{$folderId}.json", json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function &findNode(array &$tree, string $id): ?array {
    if ($tree['id'] === $id) return $tree;
    if (isset($tree['children'])) {
        foreach ($tree['children'] as &$child) {
            $result = &findNode($child, $id);
            if ($result !== null) return $result;
        }
    }
    $null = null;
    return $null;
}

function findParentNode(array &$tree, string $id, ?array &$parent = null): ?array {
    if ($tree['id'] === $id) return $parent;
    if (isset($tree['children'])) {
        foreach ($tree['children'] as &$child) {
            $result = findParentNode($child, $id, $tree);
            if ($result !== null) return $result;
        }
    }
    return null;
}

function removeNodeFromParent(array &$tree, string $id): ?array {
    if (isset($tree['children'])) {
        foreach ($tree['children'] as $i => $child) {
            if ($child['id'] === $id) {
                $removed = $tree['children'][$i];
                array_splice($tree['children'], $i, 1);
                return $removed;
            }
            $result = removeNodeFromParent($tree['children'][$i], $id);
            if ($result !== null) return $result;
        }
    }
    return null;
}

function deepCopyFolder(array $node): array {
    $newId = generateId();
    $newNode = [
        'id' => $newId,
        'name' => $node['name'] . ' (Kopie)',
        'children' => [],
        'shareTokens' => [],
    ];

    // Copy folder data
    $originalData = loadFolderData($node['id']);
    $newData = $originalData;
    $newData['id'] = $newId;
    $newData['data'] = [];
    $newData['released'] = false;

    // Copy uploaded canvas image if exists
    if (!empty($originalData['canvasImage'])) {
        $srcFile = UPLOADS_PATH . '/' . basename($originalData['canvasImage']);
        if (file_exists($srcFile)) {
            $ext = pathinfo($srcFile, PATHINFO_EXTENSION);
            $newFileName = $newId . '_canvas.' . $ext;
            copy($srcFile, UPLOADS_PATH . '/' . $newFileName);
            $newData['canvasImage'] = '/uploads/' . $newFileName;
        }
    }

    saveFolderData($newId, $newData);

    // Recursively copy children
    if (!empty($node['children'])) {
        foreach ($node['children'] as $child) {
            $newNode['children'][] = deepCopyFolder($child);
        }
    }

    return $newNode;
}

function collectFolderIds(array $node): array {
    $ids = [$node['id']];
    if (!empty($node['children'])) {
        foreach ($node['children'] as $child) {
            $ids = array_merge($ids, collectFolderIds($child));
        }
    }
    return $ids;
}

function findNodeByShareToken(array $tree, string $token): ?array {
    if (in_array($token, $tree['shareTokens'] ?? [])) return $tree;
    if (isset($tree['children'])) {
        foreach ($tree['children'] as $child) {
            $result = findNodeByShareToken($child, $token);
            if ($result !== null) return $result;
        }
    }
    return null;
}

function getSubtreeIds(array $node): array {
    return collectFolderIds($node);
}
