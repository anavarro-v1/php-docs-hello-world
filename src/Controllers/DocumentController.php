<?php

namespace App\Controllers;

use App\Services\AzureBlobClient;

/**
 * Document Controller
 * 
 * Handles HTTP requests for document management operations
 */
class DocumentController
{
    private AzureBlobClient $blobClient;
    
    public function __construct()
    {
        $this->blobClient = new AzureBlobClient();
    }
    
    /**
     * Handle file upload from form data
     */
    public function upload(): array
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return $this->errorResponse('Method not allowed', 405);
            }
            
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                return $this->errorResponse('No file uploaded or upload error occurred', 400);
            }
            
            $file = $_FILES['file'];
            $fileName = $file['name'];
            $content = file_get_contents($file['tmp_name']);
            $contentType = $file['type'] ?: 'application/octet-stream';
            
            // Handle optional path parameter
            $path = $_POST['path'] ?? '';
            $path = $this->sanitizePath($path);
            
            // Combine path with filename
            $fullFileName = $path ? $path . '/' . $fileName : $fileName;
            
            // Sanitize filename
            $fullFileName = $this->sanitizeFileName($fullFileName);
            
            return $this->blobClient->uploadDocument($fullFileName, $content, $contentType);
            
        } catch (\Exception $e) {
            return $this->errorResponse('Upload failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Handle file upload from raw body (for API clients)
     */
    public function uploadRaw(): array
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return $this->errorResponse('Method not allowed', 405);
            }
            
            $fileName = $_GET['filename'] ?? 'uploaded_file_' . time();
            $path = $_GET['path'] ?? '';
            $contentType = $_SERVER['CONTENT_TYPE'] ?? 'application/octet-stream';
            
            $content = file_get_contents('php://input');
            
            if (empty($content)) {
                return $this->errorResponse('No content provided', 400);
            }
            
            // Handle path
            $path = $this->sanitizePath($path);
            $fullFileName = $path ? $path . '/' . $fileName : $fileName;
            $fullFileName = $this->sanitizeFileName($fullFileName);
            
            return $this->blobClient->uploadDocument($fullFileName, $content, $contentType);
            
        } catch (\Exception $e) {
            return $this->errorResponse('Upload failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Retrieve a document
     */
    public function retrieve(): array
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                return $this->errorResponse('Method not allowed', 405);
            }
            
            $fileName = $_GET['filename'] ?? '';
            
            if (empty($fileName)) {
                return $this->errorResponse('Filename parameter is required', 400);
            }
            
            return $this->blobClient->retrieveDocument($fileName);
            
        } catch (\Exception $e) {
            return $this->errorResponse('Retrieve failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Delete a document
     */
    public function delete(): array
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
                return $this->errorResponse('Method not allowed', 405);
            }
            
            $fileName = $_GET['filename'] ?? '';
            
            if (empty($fileName)) {
                return $this->errorResponse('Filename parameter is required', 400);
            }
            
            return $this->blobClient->deleteDocument($fileName);
            
        } catch (\Exception $e) {
            return $this->errorResponse('Delete failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * List all documents
     */
    public function list(): array
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                return $this->errorResponse('Method not allowed', 405);
            }
            
            return $this->blobClient->listDocuments();
            
        } catch (\Exception $e) {
            return $this->errorResponse('List failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Download a document (returns file content directly)
     */
    public function download(): void
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                return;
            }
            
            $fileName = $_GET['filename'] ?? '';
            
            if (empty($fileName)) {
                http_response_code(400);
                echo json_encode(['error' => 'Filename parameter is required']);
                return;
            }
            
            $result = $this->blobClient->retrieveDocument($fileName);
            
            if (!$result['success']) {
                http_response_code($result['error_code'] ?? 500);
                echo json_encode(['error' => $result['message']]);
                return;
            }
            
            // Set appropriate headers for file download
            header('Content-Type: ' . $result['contentType']);
            header('Content-Length: ' . $result['size']);
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            
            echo $result['content'];
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Download failed: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Sanitize filename to prevent path traversal and invalid characters
     */
    private function sanitizeFileName(string $fileName): string
    {
        // For paths with slashes, handle each segment separately
        if (strpos($fileName, '/') !== false) {
            $segments = explode('/', $fileName);
            $sanitizedSegments = [];
            
            foreach ($segments as $segment) {
                if (empty($segment)) continue;
                
                // Remove directory traversal attempts
                $segment = basename($segment);
                
                // Remove or replace invalid characters
                $segment = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $segment);
                
                if (!empty($segment)) {
                    $sanitizedSegments[] = $segment;
                }
            }
            
            $fileName = implode('/', $sanitizedSegments);
        } else {
            // Remove directory traversal attempts
            $fileName = basename($fileName);
            
            // Remove or replace invalid characters
            $fileName = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $fileName);
        }
        
        // Ensure filename is not empty and has reasonable length
        if (empty($fileName) || strlen($fileName) > 1024) {
            $fileName = 'file_' . time() . '.bin';
        }
        
        return $fileName;
    }
    
    /**
     * Sanitize path to prevent path traversal and invalid characters
     */
    private function sanitizePath(string $path): string
    {
        if (empty($path)) {
            return '';
        }
        
        // Remove leading and trailing slashes
        $path = trim($path, '/');
        
        if (empty($path)) {
            return '';
        }
        
        // Split path into segments
        $segments = explode('/', $path);
        $sanitizedSegments = [];
        
        foreach ($segments as $segment) {
            // Skip empty segments and directory traversal attempts
            if (empty($segment) || $segment === '.' || $segment === '..') {
                continue;
            }
            
            // Remove or replace invalid characters (allow letters, numbers, hyphens, underscores)
            $segment = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $segment);
            
            // Ensure segment is not empty after sanitization
            if (!empty($segment) && strlen($segment) <= 255) {
                $sanitizedSegments[] = $segment;
            }
        }
        
        return implode('/', $sanitizedSegments);
    }
    
    /**
     * Generate error response
     */
    private function errorResponse(string $message, int $code = 500): array
    {
        return [
            'success' => false,
            'message' => $message,
            'error_code' => $code
        ];
    }
}
