<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';

corsHeaders();

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Remove /api prefix
$uri = preg_replace('#^/api#', '', $uri);

// Route matching
$routes = [
    // Auth
    'POST /auth/login' => 'handleLogin',
    // Folders (admin)
    'GET /folders/tree' => 'handleGetTree',
    'POST /folders' => 'handleCreateFolder',
    'PUT /folders/move' => 'handleMoveFolder',
    'PUT /folders/{id}' => 'handleUpdateFolder',
    'POST /folders/{id}/copy' => 'handleCopyFolder',
    'DELETE /folders/{id}' => 'handleDeleteFolder',
    'POST /folders/{id}/share' => 'handleCreateShare',
    'DELETE /folders/{id}/share/{token}' => 'handleDeleteShare',
    // Folder data / fields (admin)
    'GET /folders/{id}/data' => 'handleGetFolderData',
    'PUT /folders/{id}/fields' => 'handleUpdateFields',
    'PUT /folders/{id}/canvas' => 'handleUpdateCanvas',
    // Upload
    'POST /upload' => 'handleUpload',
    // Public access
    'GET /public/{token}' => 'handlePublicGetTree',
    'GET /public/{token}/folder/{id}' => 'handlePublicGetFolder',
    'PUT /public/{token}/folder/{id}' => 'handlePublicSaveData',
    'POST /public/{token}/folder/{id}/release' => 'handlePublicRelease',
    'POST /public/upload' => 'handleUpload',
    // Download
    'GET /download/{id}' => 'handleDownloadZip',
    'GET /public/{token}/download/{id}' => 'handlePublicDownloadZip',
];

$matched = false;
foreach ($routes as $route => $handler) {
    [$routeMethod, $routePath] = explode(' ', $route, 2);
    if ($method !== $routeMethod) continue;

    $pattern = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $routePath);
    if (preg_match("#^{$pattern}$#", $uri, $matches)) {
        $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        $handler($params);
        $matched = true;
        break;
    }
}

if (!$matched) {
    jsonError('Not found', 404);
}

// ===== Auth =====
function handleLogin(): void {
    $body = getJsonBody();
    $username = $body['username'] ?? '';
    $password = $body['password'] ?? '';

    if ($username === ADMIN_USERNAME && password_verify($password, ADMIN_PASSWORD)) {
        $token = createJwt(['role' => 'admin', 'username' => $username]);
        jsonResponse(['token' => $token, 'username' => $username]);
    }
    jsonError('Invalid credentials', 401);
}

// ===== Folder Tree =====
function handleGetTree(): void {
    requireAuth();
    jsonResponse(loadFolderTree());
}

function handleCreateFolder(array $params): void {
    requireAuth();
    $body = getJsonBody();
    $parentId = $body['parentId'] ?? 'root';
    $name = $body['name'] ?? 'Neuer Ordner';

    $tree = loadFolderTree();
    $parent = &findNode($tree, $parentId);
    if (!$parent) jsonError('Parent not found');

    $newId = generateId();
    $newFolder = [
        'id' => $newId,
        'name' => $name,
        'children' => [],
        'shareTokens' => [],
    ];

    $parent['children'][] = $newFolder;
    saveFolderTree($tree);

    // Initialize folder data
    saveFolderData($newId, [
        'id' => $newId,
        'canvasImage' => null,
        'fields' => [],
        'data' => [],
        'released' => false,
    ]);

    jsonResponse($newFolder, 201);
}

function handleUpdateFolder(array $params): void {
    requireAuth();
    $body = getJsonBody();
    $tree = loadFolderTree();
    $node = &findNode($tree, $params['id']);
    if (!$node) jsonError('Folder not found');

    if (isset($body['name'])) $node['name'] = $body['name'];
    saveFolderTree($tree);
    jsonResponse($node);
}

function handleMoveFolder(): void {
    requireAuth();
    $body = getJsonBody();
    $folderId = $body['folderId'] ?? '';
    $newParentId = $body['newParentId'] ?? '';
    $position = $body['position'] ?? null;

    if (!$folderId || !$newParentId) jsonError('Missing folderId or newParentId');

    $tree = loadFolderTree();
    $removed = removeNodeFromParent($tree, $folderId);
    if (!$removed) jsonError('Folder not found');

    $newParent = &findNode($tree, $newParentId);
    if (!$newParent) jsonError('New parent not found');

    if ($position !== null && $position >= 0 && $position <= count($newParent['children'])) {
        array_splice($newParent['children'], $position, 0, [$removed]);
    } else {
        $newParent['children'][] = $removed;
    }

    saveFolderTree($tree);
    jsonResponse($tree);
}

function handleCopyFolder(array $params): void {
    requireAuth();
    $tree = loadFolderTree();
    $sourceNode = &findNode($tree, $params['id']);
    if (!$sourceNode) jsonError('Folder not found');

    // Find parent of source to add copy there
    $body = getJsonBody();
    $targetParentId = $body['targetParentId'] ?? null;

    if ($targetParentId) {
        $parent = &findNode($tree, $targetParentId);
    } else {
        // Find the actual parent
        $parent = null;
        $findParent = function(array &$node, string $id) use (&$findParent, &$parent) {
            foreach ($node['children'] as &$child) {
                if ($child['id'] === $id) {
                    $parent = &$node;
                    return;
                }
                $findParent($child, $id);
            }
        };
        $findParent($tree, $params['id']);
        if (!$parent) $parent = &$tree;
    }

    $copy = deepCopyFolder($sourceNode);
    $parent['children'][] = $copy;
    saveFolderTree($tree);

    jsonResponse($copy, 201);
}

function handleDeleteFolder(array $params): void {
    requireAuth();
    $tree = loadFolderTree();

    if ($params['id'] === 'root') jsonError('Cannot delete root');

    $node = &findNode($tree, $params['id']);
    if (!$node) jsonError('Folder not found');

    // Delete all data files
    $ids = collectFolderIds($node);
    foreach ($ids as $id) {
        $file = FOLDERS_PATH . "/{$id}.json";
        if (file_exists($file)) unlink($file);
    }

    removeNodeFromParent($tree, $params['id']);
    saveFolderTree($tree);
    jsonResponse(['success' => true]);
}

// ===== Share =====
function handleCreateShare(array $params): void {
    requireAuth();
    $tree = loadFolderTree();
    $node = &findNode($tree, $params['id']);
    if (!$node) jsonError('Folder not found');

    $token = generateShareToken();
    $node['shareTokens'][] = $token;
    saveFolderTree($tree);

    $url = BASE_URL . '/public/' . $token;
    jsonResponse(['token' => $token, 'url' => $url]);
}

function handleDeleteShare(array $params): void {
    requireAuth();
    $tree = loadFolderTree();
    $node = &findNode($tree, $params['id']);
    if (!$node) jsonError('Folder not found');

    $node['shareTokens'] = array_values(array_filter(
        $node['shareTokens'] ?? [],
        fn($t) => $t !== $params['token']
    ));
    saveFolderTree($tree);
    jsonResponse(['success' => true]);
}

// ===== Folder Data =====
function handleGetFolderData(array $params): void {
    requireAuth();
    jsonResponse(loadFolderData($params['id']));
}

function handleUpdateFields(array $params): void {
    requireAuth();
    $body = getJsonBody();
    $data = loadFolderData($params['id']);
    $data['fields'] = $body['fields'] ?? [];
    saveFolderData($params['id'], $data);
    jsonResponse($data);
}

function handleUpdateCanvas(array $params): void {
    requireAuth();
    $body = getJsonBody();
    $data = loadFolderData($params['id']);
    $data['canvasImage'] = $body['canvasImage'] ?? null;
    saveFolderData($params['id'], $data);
    jsonResponse($data);
}

// ===== Upload =====
function handleUpload(): void {
    if (empty($_FILES['file'])) jsonError('No file uploaded');

    $file = $_FILES['file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'doc', 'docx', 'txt', 'zip'];
    if (!in_array($ext, $allowed)) jsonError('File type not allowed');

    $newName = generateId() . '.' . $ext;
    $dest = UPLOADS_PATH . '/' . $newName;
    move_uploaded_file($file['tmp_name'], $dest);

    jsonResponse([
        'url' => '/uploads/' . $newName,
        'name' => $file['name'],
        'size' => $file['size'],
    ]);
}

// ===== Public Access =====
function handlePublicGetTree(array $params): void {
    $tree = loadFolderTree();
    $node = findNodeByShareToken($tree, $params['token']);
    if (!$node) jsonError('Invalid or expired link', 404);

    // Return sanitized tree (no shareTokens)
    $sanitize = function(array $n) use (&$sanitize): array {
        $result = [
            'id' => $n['id'],
            'name' => $n['name'],
            'children' => [],
        ];
        foreach ($n['children'] ?? [] as $child) {
            $result['children'][] = $sanitize($child);
        }

        // Add completion status
        $folderData = loadFolderData($n['id']);
        $result['completion'] = calculateCompletion($folderData);

        return $result;
    };

    jsonResponse($sanitize($node));
}

function handlePublicGetFolder(array $params): void {
    $tree = loadFolderTree();
    $shareNode = findNodeByShareToken($tree, $params['token']);
    if (!$shareNode) jsonError('Invalid or expired link', 404);

    // Verify folder is within shared subtree
    $allowedIds = getSubtreeIds($shareNode);
    if (!in_array($params['id'], $allowedIds)) jsonError('Access denied', 403);

    $data = loadFolderData($params['id']);
    $data['completion'] = calculateCompletion($data);
    $data['missingFields'] = getMissingFields($data);
    jsonResponse($data);
}

function handlePublicSaveData(array $params): void {
    $tree = loadFolderTree();
    $shareNode = findNodeByShareToken($tree, $params['token']);
    if (!$shareNode) jsonError('Invalid or expired link', 404);

    $allowedIds = getSubtreeIds($shareNode);
    if (!in_array($params['id'], $allowedIds)) jsonError('Access denied', 403);

    $body = getJsonBody();
    $data = loadFolderData($params['id']);
    $data['data'] = $body['data'] ?? [];
    saveFolderData($params['id'], $data);

    $data['completion'] = calculateCompletion($data);
    $data['missingFields'] = getMissingFields($data);
    jsonResponse($data);
}

function handlePublicRelease(array $params): void {
    $tree = loadFolderTree();
    $shareNode = findNodeByShareToken($tree, $params['token']);
    if (!$shareNode) jsonError('Invalid or expired link', 404);

    $allowedIds = getSubtreeIds($shareNode);
    if (!in_array($params['id'], $allowedIds)) jsonError('Access denied', 403);

    $data = loadFolderData($params['id']);
    $missing = getMissingFields($data);
    if (!empty($missing)) {
        jsonError('Nicht alle Pflichtfelder sind ausgefüllt: ' . implode(', ', array_column($missing, 'label')));
    }

    $data['released'] = true;
    $data['releasedAt'] = date('Y-m-d H:i:s');
    saveFolderData($params['id'], $data);

    // Send email
    sendReleaseEmail($data, $shareNode['name']);

    jsonResponse($data);
}

// ===== Download =====
function handleDownloadZip(array $params): void {
    requireAuth();
    createAndSendZip($params['id']);
}

function handlePublicDownloadZip(array $params): void {
    $tree = loadFolderTree();
    $shareNode = findNodeByShareToken($tree, $params['token']);
    if (!$shareNode) jsonError('Invalid or expired link', 404);
    $allowedIds = getSubtreeIds($shareNode);
    if (!in_array($params['id'], $allowedIds)) jsonError('Access denied', 403);
    createAndSendZip($params['id']);
}

// ===== Helper functions =====
function calculateCompletion(array $folderData): array {
    $fields = $folderData['fields'] ?? [];
    $data = $folderData['data'] ?? [];

    $total = 0;
    $filled = 0;
    $requiredTotal = 0;
    $requiredFilled = 0;

    foreach ($fields as $field) {
        $total++;
        $value = $data[$field['id']] ?? null;
        $isFilled = isFieldFilled($field, $value);

        if ($isFilled) $filled++;

        if ($field['required'] ?? false) {
            $requiredTotal++;
            if ($isFilled) $requiredFilled++;
        }
    }

    return [
        'total' => $total,
        'filled' => $filled,
        'requiredTotal' => $requiredTotal,
        'requiredFilled' => $requiredFilled,
        'percent' => $total > 0 ? round(($filled / $total) * 100) : 100,
        'requiredPercent' => $requiredTotal > 0 ? round(($requiredFilled / $requiredTotal) * 100) : 100,
    ];
}

function isFieldFilled(array $field, mixed $value): bool {
    if ($value === null || $value === '' || $value === []) return false;

    if ($field['type'] === 'repeatable') {
        $minEntries = $field['validation']['minEntries'] ?? 1;
        if (!is_array($value) || count($value) < $minEntries) return false;
        return true;
    }

    if (is_string($value)) {
        $minLength = $field['validation']['minLength'] ?? 0;
        if ($minLength > 0 && mb_strlen(strip_tags($value)) < $minLength) return false;
    }

    if ($field['type'] === 'upload' || $field['type'] === 'multi-upload') {
        if (is_array($value) && empty($value)) return false;
    }

    return true;
}

function getMissingFields(array $folderData): array {
    $fields = $folderData['fields'] ?? [];
    $data = $folderData['data'] ?? [];
    $missing = [];

    foreach ($fields as $field) {
        if (!($field['required'] ?? false)) continue;
        $value = $data[$field['id']] ?? null;
        if (!isFieldFilled($field, $value)) {
            $missing[] = [
                'id' => $field['id'],
                'label' => $field['label'],
                'type' => $field['type'],
            ];
        }
    }

    return $missing;
}

function sendReleaseEmail(array $data, string $folderName): void {
    $subject = "Formular freigegeben: {$folderName}";
    $body = "Das Formular '{$folderName}' wurde freigegeben.\n\n";
    $body .= "Freigegeben am: " . ($data['releasedAt'] ?? date('Y-m-d H:i:s')) . "\n\n";
    $body .= "=== Daten ===\n\n";

    $fields = $data['fields'] ?? [];
    $fieldMap = [];
    foreach ($fields as $f) $fieldMap[$f['id']] = $f;

    foreach ($data['data'] ?? [] as $fieldId => $value) {
        $field = $fieldMap[$fieldId] ?? null;
        $label = $field['label'] ?? $fieldId;
        if (is_array($value)) {
            $body .= "{$label}: " . json_encode($value, JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            $body .= "{$label}: {$value}\n";
        }
    }

    @mail(MAIL_TO, $subject, $body, "From: " . MAIL_FROM . "\r\nContent-Type: text/plain; charset=UTF-8");
}

function createAndSendZip(string $folderId): void {
    $tree = loadFolderTree();
    $node = &findNode($tree, $folderId);
    if (!$node) jsonError('Folder not found');

    $zipFile = tempnam(sys_get_temp_dir(), 'formzip_');
    $zip = new ZipArchive();
    $zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    addFolderToZip($zip, $node, '');

    $zip->close();

    $name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $node['name']);
    header('Content-Type: application/zip');
    header("Content-Disposition: attachment; filename=\"{$name}.zip\"");
    header('Content-Length: ' . filesize($zipFile));
    readfile($zipFile);
    unlink($zipFile);
    exit;
}

function addFolderToZip(ZipArchive $zip, array $node, string $prefix): void {
    $path = $prefix ? "{$prefix}/{$node['name']}" : $node['name'];
    $zip->addEmptyDir($path);

    $data = loadFolderData($node['id']);
    $fields = $data['fields'] ?? [];
    $values = $data['data'] ?? [];
    $fieldMap = [];
    foreach ($fields as $f) $fieldMap[$f['id']] = $f;

    // Create text file with data
    $txt = "=== {$node['name']} ===\n\n";
    foreach ($fields as $field) {
        $value = $values[$field['id']] ?? '';
        $txt .= "{$field['label']}:\n";
        if (is_array($value)) {
            if ($field['type'] === 'repeatable') {
                foreach ($value as $i => $entry) {
                    $txt .= "  Eintrag " . ($i + 1) . ":\n";
                    foreach ($entry as $k => $v) {
                        $txt .= "    {$k}: {$v}\n";
                    }
                }
            } else {
                foreach ($value as $v) {
                    if (is_array($v)) {
                        $txt .= "  " . ($v['name'] ?? $v['url'] ?? json_encode($v)) . "\n";
                    } else {
                        $txt .= "  {$v}\n";
                    }
                }
            }
        } else {
            $txt .= "  " . strip_tags($value) . "\n";
        }
        $txt .= "\n";
    }
    $zip->addFromString("{$path}/daten.txt", $txt);

    // Add uploaded files
    foreach ($values as $fieldId => $value) {
        $field = $fieldMap[$fieldId] ?? null;
        if (!$field) continue;

        if (in_array($field['type'], ['upload', 'multi-upload'])) {
            $files = is_array($value) ? $value : [$value];
            foreach ($files as $fileInfo) {
                if (is_array($fileInfo)) {
                    $url = $fileInfo['url'] ?? '';
                    $name = $fileInfo['name'] ?? basename($url);
                } else {
                    $url = $fileInfo;
                    $name = basename($url);
                }
                $localPath = UPLOADS_PATH . '/' . basename($url);
                if (file_exists($localPath)) {
                    $zip->addFile($localPath, "{$path}/{$name}");
                }
            }
        }

        if ($field['type'] === 'repeatable' && is_array($value)) {
            foreach ($value as $entry) {
                foreach ($entry as $subValue) {
                    if (is_array($subValue)) {
                        foreach ($subValue as $fileInfo) {
                            if (is_array($fileInfo) && isset($fileInfo['url'])) {
                                $localPath = UPLOADS_PATH . '/' . basename($fileInfo['url']);
                                $name = $fileInfo['name'] ?? basename($fileInfo['url']);
                                if (file_exists($localPath)) {
                                    $zip->addFile($localPath, "{$path}/{$name}");
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    // Canvas image
    if (!empty($data['canvasImage'])) {
        $localPath = UPLOADS_PATH . '/' . basename($data['canvasImage']);
        if (file_exists($localPath)) {
            $zip->addFile($localPath, "{$path}/canvas_" . basename($data['canvasImage']));
        }
    }

    // Recurse children
    foreach ($node['children'] ?? [] as $child) {
        addFolderToZip($zip, $child, $path);
    }
}
