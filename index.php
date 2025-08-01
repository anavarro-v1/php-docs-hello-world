<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Controllers\DocumentController;
use App\Utils\DotEnvLoader;

// Load environment variables from .env file
DotEnvLoader::load(__DIR__ . '/.env');

// Set content type to JSON for API responses
header('Content-Type: application/json');

// Enable CORS for development (configure appropriately for production)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Simple routing based on path
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    // Check if Azure Storage is configured for endpoints that need it
    $azureEndpoints = ['/upload', '/upload-raw', '/retrieve', '/download', '/delete', '/list'];
    if (in_array($path, $azureEndpoints)) {
        $connectionString = getenv('AZURE_STORAGE_CONNECTION_STRING');
        if (empty($connectionString) || $connectionString === 'your_connection_string_here') {
            http_response_code(503);
            echo json_encode([
                'success' => false,
                'message' => 'Azure Storage not configured. Please set AZURE_STORAGE_CONNECTION_STRING environment variable.',
                'error_code' => 503,
                'configuration_help' => [
                    'step_1' => 'Create an Azure Storage Account',
                    'step_2' => 'Get connection string from Azure Portal > Storage Account > Access Keys',
                    'step_3' => 'Set AZURE_STORAGE_CONNECTION_STRING environment variable',
                    'step_4' => 'Format: DefaultEndpointsProtocol=https;AccountName=<name>;AccountKey=<key>;EndpointSuffix=core.windows.net'
                ]
            ], JSON_PRETTY_PRINT);
            exit;
        }
    }
    
    $controller = new DocumentController();
    
    switch ($path) {
        case '/':
            // Display HTML interface with file listing
            header('Content-Type: text/html');
            displayFileManagerInterface($controller);
            break;
            
        case '/upload':
            $result = $controller->upload();
            http_response_code($result['success'] ? 200 : ($result['error_code'] ?? 500));
            echo json_encode($result, JSON_PRETTY_PRINT);
            break;
            
        case '/upload-raw':
            $result = $controller->uploadRaw();
            http_response_code($result['success'] ? 200 : ($result['error_code'] ?? 500));
            echo json_encode($result, JSON_PRETTY_PRINT);
            break;
            
        case '/retrieve':
            $result = $controller->retrieve();
            http_response_code($result['success'] ? 200 : ($result['error_code'] ?? 500));
            // Don't include content in JSON response for retrieve (use download for content)
            if (isset($result['content'])) {
                $result['content'] = '[Content available via /download endpoint]';
                $result['content_preview'] = substr(base64_encode($result['content']), 0, 100) . '...';
            }
            echo json_encode($result, JSON_PRETTY_PRINT);
            break;
            
        case '/download':
            // This endpoint returns file content directly, not JSON
            header('Content-Type: application/octet-stream');
            $controller->download();
            break;
            
        case '/delete':
            $result = $controller->delete();
            http_response_code($result['success'] ? 200 : ($result['error_code'] ?? 500));
            echo json_encode($result, JSON_PRETTY_PRINT);
            break;
            
        case '/list':
            $result = $controller->list();
            http_response_code($result['success'] ? 200 : ($result['error_code'] ?? 500));
            echo json_encode($result, JSON_PRETTY_PRINT);
            break;
            
        case '/assets/js/file-manager.js':
            // Serve the JavaScript file
            header('Content-Type: application/javascript');
            readfile(__DIR__ . '/assets/js/file-manager.js');
            break;
            
        default:
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Endpoint not found',
                'error_code' => 404,
                'available_endpoints' => ['/upload', '/upload-raw', '/retrieve', '/download', '/delete', '/list']
            ], JSON_PRETTY_PRINT);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage(),
        'error_code' => 500
    ], JSON_PRETTY_PRINT);
}

/**
 * Display the file manager interface
 */
function displayFileManagerInterface($controller) {
    // Get list of files
    $fileListResult = $controller->list();
    $files = $fileListResult['success'] ? $fileListResult['documents'] : [];
    
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Azure Blob Storage File Manager</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #0078d4 0%, #106ebe 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 300;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .upload-section {
            padding: 30px;
            border-bottom: 1px solid #eee;
            background: #f8f9fa;
        }
        
        .upload-form {
            display: flex;
            align-items: center;
            gap: 15px;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .file-input-wrapper {
            position: relative;
            flex: 2;
        }
        
        .path-input-wrapper {
            position: relative;
            flex: 1;
        }
        
        .file-input, .path-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px dashed #ddd;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .path-input {
            border-style: solid;
            cursor: text;
        }
        
        .file-input:hover, .path-input:hover {
            border-color: #0078d4;
            background: #f0f8ff;
        }
        
        .path-input:focus {
            outline: none;
            border-color: #0078d4;
            background: #f0f8ff;
        }
        
        .upload-btn {
            background: #0078d4;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s ease;
        }
        
        .upload-btn:hover {
            background: #106ebe;
        }
        
        .files-section {
            padding: 30px;
        }
        
        .folder-section {
            margin-bottom: 30px;
        }
        
        .folder-title {
            color: #0078d4;
            font-size: 1.2rem;
            margin-bottom: 15px;
            padding: 10px 0;
            border-bottom: 2px solid #e0e0e0;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .folder-title:hover {
            background-color: #f0f8ff;
            padding-left: 10px;
            padding-right: 10px;
            border-radius: 6px;
        }
        
        .folder-toggle {
            font-size: 0.8rem;
            color: #666;
            transition: transform 0.3s ease;
        }
        
        .folder-content {
            max-height: 2000px;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }
        
        .folder-content.collapsed {
            max-height: 0;
        }
        
        .folder-toggle.collapsed {
            transform: rotate(-90deg);
        }
        
        .files-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .folder-controls {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .folder-control-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: background 0.3s ease;
        }
        
        .folder-control-btn:hover {
            background: #5a6268;
        }
        
        .files-count {
            color: #666;
            font-size: 1.1rem;
        }
        
        .refresh-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s ease;
        }
        
        .refresh-btn:hover {
            background: #218838;
        }
        
        .files-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .file-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            transition: all 0.3s ease;
            position: relative;
            cursor: pointer;
        }
        
        .file-card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transform: translateY(-2px);
            border-color: #0078d4;
        }
        
        .file-icon {
            width: 48px;
            height: 48px;
            margin-bottom: 15px;
            background: #f0f8ff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            overflow: hidden;
        }
        
        .file-icon.image-preview {
            background: transparent;
            border: 2px solid #e0e0e0;
        }
        
        .file-icon.image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 6px;
        }
        
        .file-name {
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
            word-break: break-word;
        }
        
        .file-meta {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .file-actions {
            display: flex;
            gap: 10px;
        }
        
        .image-controls {
            display: flex;
            gap: 5px;
            margin-bottom: 10px;
            justify-content: center;
        }
        
        .image-control-btn {
            background: #f8f9fa;
            color: #495057;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 4px 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            min-width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .image-control-btn:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }
        
        .image-control-btn:active {
            background: #dee2e6;
        }
        
        .action-btn {
            flex: 1;
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .download-btn {
            background: #0078d4;
            color: white;
        }
        
        .download-btn:hover {
            background: #106ebe;
        }
        
        .delete-btn {
            background: #dc3545;
            color: white;
        }
        
        .delete-btn:hover {
            background: #c82333;
        }
        
        .tooltip {
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%) translateY(-100%);
            background: #333;
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 12px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
            z-index: 1000;
            max-width: 300px;
            text-align: left;
            line-height: 1.4;
        }
        
        .tooltip.image-tooltip {
            max-width: 400px;
            white-space: normal;
        }
        
        .tooltip.image-tooltip img {
            width: 200px;
            height: auto;
            border-radius: 4px;
            margin-top: 8px;
            display: block;
        }
        
        .tooltip::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 5px solid transparent;
            border-top-color: #333;
        }
        
        .file-card:hover .tooltip {
            opacity: 1;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .message {
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
            text-align: center;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .message.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .processing {
            opacity: 0.6;
            pointer-events: none;
        }
        
        @media (max-width: 768px) {
            .files-grid {
                grid-template-columns: 1fr;
            }
            
            .upload-form {
                flex-direction: column;
            }
            
            .file-input-wrapper, .path-input-wrapper {
                width: 100%;
            }
            
            .files-header {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .folder-controls {
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .folder-title {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📁 Azure Blob Storage</h1>
            <p>File Manager & Document Storage</p>
        </div>
        
        <div class="upload-section">
            <form class="upload-form" id="uploadForm" enctype="multipart/form-data">
                <div class="file-input-wrapper">
                    <input type="file" class="file-input" id="fileInput" name="file" required>
                </div>
                <div class="path-input-wrapper">
                    <input type="text" class="path-input" id="pathInput" name="path" placeholder="Optional: folder/subfolder/" title="Specify a folder path (e.g., images/, documents/pdf/)">
                </div>
                <button type="submit" class="upload-btn">📤 Upload</button>
            </form>
            <div id="uploadMessage"></div>
        </div>
        
        <div class="files-section">
            <div class="files-header">
                <h2 class="files-count">📂 ' . count($files) . ' file' . (count($files) !== 1 ? 's' : '') . ' stored</h2>
                <div class="folder-controls">
                    <button class="folder-control-btn" onclick="toggleAllFolders(false)" title="Collapse all folders">📁 Collapse All</button>
                    <button class="folder-control-btn" onclick="toggleAllFolders(true)" title="Expand all folders">📂 Expand All</button>
                    <button class="refresh-btn" onclick="location.reload()">🔄 Refresh</button>
                </div>
            </div>';
    
    if (empty($files)) {
        echo '<div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <h3>No files uploaded yet</h3>
                <p>Upload your first file using the form above</p>
              </div>';
    } else {
        // Group files by folder
        $filesByFolder = [];
        foreach ($files as $file) {
            $pathParts = explode('/', $file['name']);
            if (count($pathParts) > 1) {
                $folder = implode('/', array_slice($pathParts, 0, -1));
                $file['displayName'] = end($pathParts);
                $file['folder'] = $folder;
            } else {
                $folder = 'Root';
                $file['displayName'] = $file['name'];
                $file['folder'] = '';
            }
            $filesByFolder[$folder][] = $file;
        }
        
        // Sort folders
        ksort($filesByFolder);
        
        foreach ($filesByFolder as $folderName => $folderFiles) {
            $folderId = 'folder-' . md5($folderName);
            
            if ($folderName !== 'Root') {
                echo '<div class="folder-section">
                        <div class="folder-title" onclick="toggleFolder(\'' . $folderId . '\')">
                            <span>📁 ' . htmlspecialchars($folderName) . ' (' . count($folderFiles) . ' files)</span>
                            <span class="folder-toggle" id="toggle-' . $folderId . '">▼</span>
                        </div>
                        <div class="folder-content" id="' . $folderId . '">';
            }
            
            echo '<div class="files-grid">';
            
            foreach ($folderFiles as $file) {
                $fileExt = pathinfo($file['displayName'], PATHINFO_EXTENSION);
                $isImage = isImageFile($fileExt);
                $icon = $isImage ? '' : getFileIcon($fileExt);
                $size = formatFileSize($file['size']);
                $lastModified = date('M j, Y g:i A', strtotime($file['lastModified']));
                
                echo '<div class="file-card">
                        <div class="tooltip' . ($isImage ? ' image-tooltip' : '') . '">
                            <strong>' . htmlspecialchars($file['name']) . '</strong><br>
                            Size: ' . $size . '<br>
                            Type: ' . htmlspecialchars($file['contentType']) . '<br>
                            Modified: ' . $lastModified . '<br>
                            ETag: ' . htmlspecialchars($file['etag']);
                
                if ($isImage) {
                    echo '<br><img src="' . htmlspecialchars($file['url']) . '" alt="Preview" />';
                }
                
                echo '</div>';
                
                if ($isImage) {
                    echo '<div class="file-icon image-preview">
                            <img src="' . htmlspecialchars($file['url']) . '" alt="' . htmlspecialchars($file['displayName']) . '" />
                          </div>';
                } else {
                    echo '<div class="file-icon">' . $icon . '</div>';
                }
                
                echo '<div class="file-name">' . htmlspecialchars($file['displayName']) . '</div>
                        <div class="file-meta">
                            ' . $size . ' • ' . $lastModified . '
                        </div>';
                
                if ($isImage) {
                    echo '<div class="image-controls">
                            <button class="image-control-btn" onclick="rotateImage(\'' . htmlspecialchars($file['name'], ENT_QUOTES) . '\', 90)" title="Rotate 90° clockwise">↻</button>
                            <button class="image-control-btn" onclick="rotateImage(\'' . htmlspecialchars($file['name'], ENT_QUOTES) . '\', -90)" title="Rotate 90° counter-clockwise">↺</button>
                            <button class="image-control-btn" onclick="flipImage(\'' . htmlspecialchars($file['name'], ENT_QUOTES) . '\', \'horizontal\')" title="Flip horizontally">⇄</button>
                            <button class="image-control-btn" onclick="flipImage(\'' . htmlspecialchars($file['name'], ENT_QUOTES) . '\', \'vertical\')" title="Flip vertically">⇅</button>
                          </div>';
                }
                
                echo '<div class="file-actions">
                            <button class="action-btn download-btn" onclick="downloadFile(\'' . htmlspecialchars($file['name'], ENT_QUOTES) . '\')">
                                ⬇️ Download
                            </button>
                            <button class="action-btn delete-btn" onclick="deleteFile(\'' . htmlspecialchars($file['name'], ENT_QUOTES) . '\')">
                                🗑️ Delete
                            </button>
                        </div>
                      </div>';
            }
            
            echo '</div>';
            
            if ($folderName !== 'Root') {
                echo '</div></div>';
            }
        }
    }
    
    echo '    </div>
    </div>
    
    <script src="/assets/js/file-manager.js"></script>
</body>
</html>';
}

/**
 * Check if file is an image based on extension
 */
function isImageFile($extension) {
    $extension = strtolower($extension);
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
    return in_array($extension, $imageExtensions);
}

/**
 * Get file icon based on extension
 */
function getFileIcon($extension) {
    $extension = strtolower($extension);
    
    $icons = [
        'pdf' => '📄',
        'doc' => '📝',
        'docx' => '📝',
        'xls' => '📊',
        'xlsx' => '📊',
        'ppt' => '📽️',
        'pptx' => '📽️',
        'txt' => '📄',
        'jpg' => '🖼️',
        'jpeg' => '🖼️',
        'png' => '🖼️',
        'gif' => '🖼️',
        'mp4' => '🎬',
        'avi' => '🎬',
        'mov' => '🎬',
        'mp3' => '🎵',
        'wav' => '🎵',
        'zip' => '📦',
        'rar' => '📦',
        'html' => '🌐',
        'css' => '🎨',
        'js' => '⚡',
        'php' => '🐘',
        'py' => '🐍',
        'json' => '📋',
        'xml' => '📋',
    ];
    
    return $icons[$extension] ?? '📄';
}

/**
 * Format file size in human readable format
 */
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 Bytes';
    
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes) / log($k));
    
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
