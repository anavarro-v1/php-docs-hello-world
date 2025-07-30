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
            // Welcome message and API documentation
            echo json_encode([
                'message' => 'Welcome to PHP Hello World with Azure Storage Blob API',
                'version' => '1.0.0',
                'endpoints' => [
                    'GET /' => 'This help message',
                    'POST /upload' => 'Upload a document (multipart/form-data with "file" field)',
                    'POST /upload-raw' => 'Upload raw document content (query param: filename)',
                    'GET /retrieve' => 'Retrieve document metadata (query param: filename)',
                    'GET /download' => 'Download document content (query param: filename)',
                    'DELETE /delete' => 'Delete a document (query param: filename)',
                    'GET /list' => 'List all documents in the container'
                ],
                'example_usage' => [
                    'upload' => 'curl -X POST -F "file=@document.pdf" http://localhost/upload',
                    'upload_raw' => 'curl -X POST --data-binary @document.pdf "http://localhost/upload-raw?filename=document.pdf"',
                    'retrieve' => 'curl "http://localhost/retrieve?filename=document.pdf"',
                    'download' => 'curl "http://localhost/download?filename=document.pdf" -o downloaded_file.pdf',
                    'delete' => 'curl -X DELETE "http://localhost/delete?filename=document.pdf"',
                    'list' => 'curl "http://localhost/list"'
                ],
                'environment_variables' => [
                    'AZURE_STORAGE_CONNECTION_STRING' => 'Required - Your Azure Storage connection string',
                    'AZURE_STORAGE_CONTAINER' => 'Optional - Container name (default: documents)'
                ]
            ], JSON_PRETTY_PRINT);
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
