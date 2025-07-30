<?php

namespace App\Services;

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\CreateBlockBlobOptions;
use MicrosoftAzure\Storage\Blob\Models\GetBlobOptions;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use App\Config\AzureStorageConfig;

/**
 * Azure Storage Blob Client Library
 * 
 * This class provides a simple interface for interacting with Azure Storage Blob service
 * for document management operations including upload, retrieve, and delete.
 */
class AzureBlobClient
{
    private BlobRestProxy $blobClient;
    private string $containerName;
    
    public function __construct()
    {
        $this->blobClient = BlobRestProxy::createBlobService(
            AzureStorageConfig::getConnectionString()
        );
        $this->containerName = AzureStorageConfig::getDefaultContainer();
        
        // Ensure container exists
        $this->ensureContainerExists();
    }
    
    /**
     * Upload a document to Azure Blob Storage
     * 
     * @param string $fileName The name of the file to store
     * @param string $content The file content
     * @param string $contentType The MIME type of the file
     * @return array Result with success status and blob URL
     */
    public function uploadDocument(string $fileName, string $content, string $contentType = 'application/octet-stream'): array
    {
        try {
            $options = new CreateBlockBlobOptions();
            $options->setContentType($contentType);
            
            // Add metadata
            $options->setMetadata([
                'uploaded_at' => date('c'),
                'original_name' => $fileName
            ]);
            
            $this->blobClient->createBlockBlob(
                $this->containerName,
                $fileName,
                $content,
                $options
            );
            
            $blobUrl = $this->getBlobUrl($fileName);
            
            return [
                'success' => true,
                'message' => 'Document uploaded successfully',
                'fileName' => $fileName,
                'url' => $blobUrl,
                'size' => strlen($content)
            ];
            
        } catch (ServiceException $e) {
            return [
                'success' => false,
                'message' => 'Failed to upload document: ' . $e->getMessage(),
                'error_code' => $e->getCode()
            ];
        }
    }
    
    /**
     * Retrieve a document from Azure Blob Storage
     * 
     * @param string $fileName The name of the file to retrieve
     * @return array Result with success status and file content
     */
    public function retrieveDocument(string $fileName): array
    {
        try {
            $blob = $this->blobClient->getBlob($this->containerName, $fileName);
            $content = stream_get_contents($blob->getContentStream());
            
            $properties = $blob->getProperties();
            $metadata = $blob->getMetadata();
            
            return [
                'success' => true,
                'message' => 'Document retrieved successfully',
                'fileName' => $fileName,
                'content' => $content,
                'contentType' => $properties->getContentType(),
                'size' => $properties->getContentLength(),
                'lastModified' => $properties->getLastModified()->format('c'),
                'metadata' => $metadata
            ];
            
        } catch (ServiceException $e) {
            if ($e->getCode() === 404) {
                return [
                    'success' => false,
                    'message' => 'Document not found',
                    'error_code' => 404
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve document: ' . $e->getMessage(),
                'error_code' => $e->getCode()
            ];
        }
    }
    
    /**
     * Delete a document from Azure Blob Storage
     * 
     * @param string $fileName The name of the file to delete
     * @return array Result with success status
     */
    public function deleteDocument(string $fileName): array
    {
        try {
            $this->blobClient->deleteBlob($this->containerName, $fileName);
            
            return [
                'success' => true,
                'message' => 'Document deleted successfully',
                'fileName' => $fileName
            ];
            
        } catch (ServiceException $e) {
            if ($e->getCode() === 404) {
                return [
                    'success' => false,
                    'message' => 'Document not found',
                    'error_code' => 404
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to delete document: ' . $e->getMessage(),
                'error_code' => $e->getCode()
            ];
        }
    }
    
    /**
     * List all documents in the container
     * 
     * @return array List of documents with metadata
     */
    public function listDocuments(): array
    {
        try {
            $blobList = $this->blobClient->listBlobs($this->containerName);
            $documents = [];
            
            foreach ($blobList->getBlobs() as $blob) {
                $documents[] = [
                    'name' => $blob->getName(),
                    'url' => $this->getBlobUrl($blob->getName()),
                    'size' => $blob->getProperties()->getContentLength(),
                    'contentType' => $blob->getProperties()->getContentType(),
                    'lastModified' => $blob->getProperties()->getLastModified()->format('c')
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Documents listed successfully',
                'documents' => $documents,
                'count' => count($documents)
            ];
            
        } catch (ServiceException $e) {
            return [
                'success' => false,
                'message' => 'Failed to list documents: ' . $e->getMessage(),
                'error_code' => $e->getCode()
            ];
        }
    }
    
    /**
     * Get the public URL for a blob
     */
    private function getBlobUrl(string $fileName): string
    {
        $accountName = AzureStorageConfig::getAccountName();
        return "https://{$accountName}.blob.core.windows.net/{$this->containerName}/{$fileName}";
    }
    
    /**
     * Ensure the container exists, create if it doesn't
     */
    private function ensureContainerExists(): void
    {
        try {
            $this->blobClient->getContainerProperties($this->containerName);
        } catch (ServiceException $e) {
            if ($e->getCode() === 404) {
                // Container doesn't exist, create it
                try {
                    $this->blobClient->createContainer($this->containerName);
                } catch (ServiceException $createException) {
                    throw new \Exception('Failed to create container: ' . $createException->getMessage());
                }
            } else {
                throw new \Exception('Failed to check container: ' . $e->getMessage());
            }
        }
    }
}
