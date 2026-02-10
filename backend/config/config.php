<?php
define('STORAGE_PATH', __DIR__ . '/../storage');
define('FOLDERS_PATH', STORAGE_PATH . '/folders');
define('UPLOADS_PATH', STORAGE_PATH . '/uploads');
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); // password
define('JWT_SECRET', 'form-builder-secret-key-change-in-production-2024');
define('MAIL_TO', 'marketing@bodner.com');
define('MAIL_FROM', 'noreply@formbuilder.local');
define('BASE_URL', 'http://localhost:8080');

// Ensure storage directories exist
if (!is_dir(FOLDERS_PATH)) mkdir(FOLDERS_PATH, 0755, true);
if (!is_dir(UPLOADS_PATH)) mkdir(UPLOADS_PATH, 0755, true);
