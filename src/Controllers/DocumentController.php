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
            
            // Sanitize filename
            $fileName = $this->sanitizeFileName($fileName);
            
            return $this->blobClient->uploadDocument($fileName, $content, $contentType);
            
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
            $contentType = $_SERVER['CONTENT_TYPE'] ?? 'application/octet-stream';
            
            $content = file_get_contents('php://input');
            
            if (empty($content)) {
                return $this->errorResponse('No content provided', 400);
            }
            
            return $this->blobClient->uploadDocument($fileName, $content, $contentType);
            
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
        // Remove directory traversal attempts
        $fileName = basename($fileName);
        
        // Remove or replace invalid characters
        $fileName = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $fileName);
        
        // Ensure filename is not empty and has reasonable length
        if (empty($fileName) || strlen($fileName) > 255) {
            $fileName = 'file_' . time() . '.bin';
        }
        
        return $fileName;
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
