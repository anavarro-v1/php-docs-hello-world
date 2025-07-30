<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Config\AzureStorageConfig;

/**
 * Azure Storage Blob REST API Client
 * 
 * This class provides a direct interface to Azure Storage Blob REST API
 * without using the deprecated SDK, for document management operations.
 */
class AzureBlobClient
{
    private Client $httpClient;
    private string $accountName;
    private string $accountKey;
    private string $containerName;
    private string $baseUrl;
    
    public function __construct()
    {
        $this->httpClient = new Client();
        $this->parseConnectionString();
        $this->containerName = AzureStorageConfig::getDefaultContainer();
        $this->baseUrl = "https://{$this->accountName}.blob.core.windows.net";
        
        // Ensure container exists
        $this->ensureContainerExists();
    }
    
    /**
     * Upload a document to Azure Blob Storage
     */
    public function uploadDocument(string $fileName, string $content, string $contentType = 'application/octet-stream'): array
    {
        try {
            $url = "{$this->baseUrl}/{$this->containerName}/{$fileName}";
            $date = gmdate('D, d M Y H:i:s T');
            
            $headers = [
                'x-ms-date' => $date,
                'x-ms-version' => '2021-12-02',
                'x-ms-blob-type' => 'BlockBlob',
                'Content-Type' => $contentType,
                'Content-Length' => strlen($content),
            ];

            $headers['Authorization'] = $this->generateSharedKeySignature('PUT', $url, $headers, $content);
            
            $response = $this->httpClient->put($url, [
                'headers' => $headers,
                'body' => $content
            ]);
            
            return [
                'success' => true,
                'message' => 'Document uploaded successfully',
                'fileName' => $fileName,
                'url' => $url,
                'size' => strlen($content),
                'etag' => $response->getHeader('ETag')[0] ?? null
            ];
            
        } catch (RequestException $e) {
            return [
                'success' => false,
                'message' => 'Failed to upload document: ' . $e->getMessage(),
                'error_code' => $e->getCode(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null
            ];
        }
    }
    
    /**
     * Retrieve a document from Azure Blob Storage
     */
    public function retrieveDocument(string $fileName): array
    {
        try {
            $url = "{$this->baseUrl}/{$this->containerName}/{$fileName}";
            $date = gmdate('D, d M Y H:i:s T');
            
            $headers = [
                'x-ms-date' => $date,
                'x-ms-version' => '2021-12-02',
            ];
            
            $headers['Authorization'] = $this->generateSharedKeySignature('GET', $url, $headers);
            
            $response = $this->httpClient->get($url, [
                'headers' => $headers
            ]);
            
            $content = $response->getBody()->getContents();
            $responseHeaders = $response->getHeaders();
            
            return [
                'success' => true,
                'message' => 'Document retrieved successfully',
                'fileName' => $fileName,
                'content' => $content,
                'contentType' => $responseHeaders['Content-Type'][0] ?? 'application/octet-stream',
                'size' => (int)($responseHeaders['Content-Length'][0] ?? 0),
                'lastModified' => $responseHeaders['Last-Modified'][0] ?? null,
                'etag' => $responseHeaders['ETag'][0] ?? null,
                'metadata' => $this->parseMetadata($responseHeaders)
            ];
            
        } catch (RequestException $e) {
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
                'error_code' => $e->getCode(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null
            ];
        }
    }
    
    /**
     * Delete a document from Azure Blob Storage
     */
    public function deleteDocument(string $fileName): array
    {
        try {
            $url = "{$this->baseUrl}/{$this->containerName}/{$fileName}";
            $date = gmdate('D, d M Y H:i:s T');
            
            $headers = [
                'x-ms-date' => $date,
                'x-ms-version' => '2021-12-02',
            ];
            
            $headers['Authorization'] = $this->generateSharedKeySignature('DELETE', $url, $headers);
            
            $this->httpClient->delete($url, [
                'headers' => $headers
            ]);
            
            return [
                'success' => true,
                'message' => 'Document deleted successfully',
                'fileName' => $fileName
            ];
            
        } catch (RequestException $e) {
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
                'error_code' => $e->getCode(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null
            ];
        }
    }
    
    /**
     * List all documents in the container
     */
    public function listDocuments(): array
    {
        try {
            $url = "{$this->baseUrl}/{$this->containerName}?restype=container&comp=list";
            $date = gmdate('D, d M Y H:i:s T');
            
            $headers = [
                'x-ms-date' => $date,
                'x-ms-version' => '2021-12-02',
            ];
            
            $headers['Authorization'] = $this->generateSharedKeySignature('GET', $url, $headers);
            
            $response = $this->httpClient->get($url, [
                'headers' => $headers
            ]);
            
            $xmlContent = $response->getBody()->getContents();
            $xml = simplexml_load_string($xmlContent);
            
            $documents = [];
            if (isset($xml->Blobs->Blob)) {
                foreach ($xml->Blobs->Blob as $blob) {
                    $documents[] = [
                        'name' => (string)$blob->Name,
                        'url' => "{$this->baseUrl}/{$this->containerName}/" . urlencode((string)$blob->Name),
                        'size' => (int)$blob->Properties->{'Content-Length'},
                        'contentType' => (string)$blob->Properties->{'Content-Type'},
                        'lastModified' => (string)$blob->Properties->{'Last-Modified'},
                        'etag' => (string)$blob->Properties->Etag
                    ];
                }
            }
            
            return [
                'success' => true,
                'message' => 'Documents listed successfully',
                'documents' => $documents,
                'count' => count($documents)
            ];
            
        } catch (RequestException $e) {
            return [
                'success' => false,
                'message' => 'Failed to list documents: ' . $e->getMessage(),
                'error_code' => $e->getCode(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null
            ];
        }
    }
    
    /**
     * Parse connection string to extract account name and key
     */
    private function parseConnectionString(): void
    {
        $connectionString = AzureStorageConfig::getConnectionString();
        
        // Parse AccountName
        if (preg_match('/AccountName=([^;]+)/', $connectionString, $matches)) {
            $this->accountName = $matches[1];
        } else {
            throw new \Exception('Could not extract AccountName from connection string');
        }
        
        // Parse AccountKey
        if (preg_match('/AccountKey=([^;]+)/', $connectionString, $matches)) {
            $this->accountKey = $matches[1];
        } else {
            throw new \Exception('Could not extract AccountKey from connection string');
        }
    }
    
    /**
     * Generate Azure Storage Shared Key Authorization signature
     */
    private function generateSharedKeySignature(string $method, string $url, array $headers, string $body = ''): string
    {
        $parsedUrl = parse_url($url);
        $canonicalizedResource = "/{$this->accountName}" . $parsedUrl['path'];
        
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $queryParams);
            ksort($queryParams);
            
            foreach ($queryParams as $key => $value) {
                $canonicalizedResource .= "\n" . strtolower($key) . ":" . $value;
            }
        }
        
        // Canonicalized headers
        $canonicalizedHeaders = '';
        $msHeaders = [];
        
        foreach ($headers as $key => $value) {
            if (strpos(strtolower($key), 'x-ms-') === 0) {
                $msHeaders[strtolower($key)] = $value;
            }
        }
        
        ksort($msHeaders);
        
        foreach ($msHeaders as $key => $value) {
            $canonicalizedHeaders .= $key . ":" . $value . "\n";
        }
        
        $canonicalizedHeaders = rtrim($canonicalizedHeaders, "\n");
        
        // String to sign
        $stringToSign = $method . "\n" .
                       ($headers['Content-Encoding'] ?? '') . "\n" .
                       ($headers['Content-Language'] ?? '') . "\n" .
                       (strlen($body) > 0 ? strlen($body) : '') . "\n" .
                       ($headers['Content-MD5'] ?? '') . "\n" .
                       ($headers['Content-Type'] ?? '') . "\n" .
                       ($headers['Date'] ?? '') . "\n" .
                       ($headers['If-Modified-Since'] ?? '') . "\n" .
                       ($headers['If-Match'] ?? '') . "\n" .
                       ($headers['If-None-Match'] ?? '') . "\n" .
                       ($headers['If-Unmodified-Since'] ?? '') . "\n" .
                       ($headers['Range'] ?? '') . "\n" .
                       $canonicalizedHeaders . "\n" .
                       $canonicalizedResource;
        
        $signature = base64_encode(hash_hmac('sha256', $stringToSign, base64_decode($this->accountKey), true));
        
        return "SharedKey {$this->accountName}:{$signature}";
    }
    
    /**
     * Parse metadata from response headers
     */
    private function parseMetadata(array $headers): array
    {
        $metadata = [];
        
        foreach ($headers as $key => $value) {
            if (strpos(strtolower($key), 'x-ms-meta-') === 0) {
                $metaKey = substr($key, 10); // Remove 'x-ms-meta-' prefix
                $metadata[$metaKey] = is_array($value) ? $value[0] : $value;
            }
        }
        
        return $metadata;
    }
    
    /**
     * Ensure the container exists, create if it doesn't
     */
    private function ensureContainerExists(): void
    {
        try {
            // Check if container exists
            $url = "{$this->baseUrl}/{$this->containerName}?restype=container";
            $date = gmdate('D, d M Y H:i:s T');
            
            $headers = [
                'x-ms-date' => $date,
                'x-ms-version' => '2021-12-02',
            ];
            
            $headers['Authorization'] = $this->generateSharedKeySignature('HEAD', $url, $headers);
            
            $this->httpClient->head($url, [
                'headers' => $headers
            ]);
            
        } catch (RequestException $e) {
            if ($e->getCode() === 404) {
                // Container doesn't exist, create it
                $this->createContainer();
            } else {
                throw new \Exception('Failed to check container: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Create a new container
     */
    private function createContainer(): void
    {
        try {
            $url = "{$this->baseUrl}/{$this->containerName}?restype=container";
            $date = gmdate('D, d M Y H:i:s T');
            
            $headers = [
                'x-ms-date' => $date,
                'x-ms-version' => '2021-12-02',
                'x-ms-blob-public-access' => 'blob', // Allow public access to blobs
            ];
            
            $headers['Authorization'] = $this->generateSharedKeySignature('PUT', $url, $headers);
            
            $this->httpClient->put($url, [
                'headers' => $headers
            ]);
            
        } catch (RequestException $e) {
            throw new \Exception('Failed to create container: ' . $e->getMessage());
        }
    }
}
