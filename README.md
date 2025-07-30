# PHP Hello World with Azure Storage Blob Integration

This sample demonstrates a PHP application with Azure Storage Blob service integration for document management. The application provides REST API endpoints for uploading, retrieving, and deleting documents stored in Azure Blob Storage.

## Features

- **REST API Client Library** for Azure Storage Blob service
- **Upload Documents** - Store files in Azure Blob Storage
- **Retrieve Documents** - Get document metadata and content
- **Delete Documents** - Remove files from storage
- **List Documents** - View all stored documents
- **Web Interface** - HTML frontend for testing the API

## Project Structure

```
├── src/
│   ├── Config/
│   │   └── AzureStorageConfig.php    # Azure Storage configuration
│   ├── Controllers/
│   │   └── DocumentController.php    # HTTP request handling
│   └── Services/
│       └── AzureBlobClient.php       # Azure Blob Storage client library
├── index.php                        # Main application with API endpoints
├── test.html                        # Web interface for testing
├── composer.json                    # Dependencies
└── .env.example                     # Environment configuration template
```

## Prerequisites

- PHP 7.4 or higher
- Composer
- Azure Storage Account

## Setup Instructions

### 1. Install Dependencies

```bash
composer install
```

### 2. Configure Azure Storage

1. Create an Azure Storage Account in the Azure Portal
2. Get your connection string from: Storage Account > Access Keys
3. Copy `.env.example` to `.env` and fill in your credentials:

```bash
cp .env.example .env
```

Edit `.env`:
```
AZURE_STORAGE_CONNECTION_STRING=DefaultEndpointsProtocol=https;AccountName=<your_account>;AccountKey=<your_key>;EndpointSuffix=core.windows.net
AZURE_STORAGE_CONTAINER=documents
```

### 3. Load Environment Variables

Add this to your web server configuration or at the top of index.php:

```php
// Load environment variables from .env file
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && !str_starts_with($line, '#')) {
            putenv(trim($line));
        }
    }
}
```

### 4. Start the Application

For development, use PHP's built-in server:

```bash
php -S localhost:8000
```

## API Endpoints

### Base URL: `http://localhost:8000`

| Method | Endpoint | Description | Parameters |
|--------|----------|-------------|------------|
| GET | `/` | API documentation and help | - |
| POST | `/upload` | Upload file (form data) | `file` (multipart form field) |
| POST | `/upload-raw` | Upload raw content | `filename` (query param), raw body |
| GET | `/retrieve` | Get document metadata | `filename` (query param) |
| GET | `/download` | Download document content | `filename` (query param) |
| DELETE | `/delete` | Delete document | `filename` (query param) |
| GET | `/list` | List all documents | - |

## Usage Examples

### Upload a Document (cURL)

```bash
# Upload using form data
curl -X POST -F "file=@document.pdf" http://localhost:8000/upload

# Upload raw content
curl -X POST --data-binary @document.pdf \
  "http://localhost:8000/upload-raw?filename=document.pdf" \
  -H "Content-Type: application/pdf"
```

### Retrieve Document Information

```bash
curl "http://localhost:8000/retrieve?filename=document.pdf"
```

### Download Document

```bash
curl "http://localhost:8000/download?filename=document.pdf" -o downloaded_file.pdf
```

### Delete Document

```bash
curl -X DELETE "http://localhost:8000/delete?filename=document.pdf"
```

### List All Documents

```bash
curl "http://localhost:8000/list"
```

## Web Interface

Open `http://localhost:8000/test.html` in your browser for a user-friendly interface to:
- Upload files via drag-and-drop or file picker
- View document information
- Download documents
- Delete documents
- List all stored documents

## Azure Storage Blob Client Library

The `AzureBlobClient` class provides a simple interface for Azure Storage operations:

```php
use App\Services\AzureBlobClient;

$client = new AzureBlobClient();

// Upload a document
$result = $client->uploadDocument('example.pdf', $fileContent, 'application/pdf');

// Retrieve a document
$result = $client->retrieveDocument('example.pdf');

// Delete a document
$result = $client->deleteDocument('example.pdf');

// List all documents
$result = $client->listDocuments();
```

## Configuration

### Environment Variables

- `AZURE_STORAGE_CONNECTION_STRING` (required): Your Azure Storage connection string
- `AZURE_STORAGE_CONTAINER` (optional): Container name for documents (default: "documents")

### Security Considerations

- The application automatically creates the storage container if it doesn't exist
- File names are sanitized to prevent path traversal attacks
- CORS is enabled for development (configure appropriately for production)
- Consider implementing authentication and authorization for production use

## Error Handling

All API responses include:
- `success`: Boolean indicating operation success
- `message`: Human-readable message
- `error_code`: HTTP status code for errors
- Additional data depending on the operation

Example error response:
```json
{
  "success": false,
  "message": "Document not found",
  "error_code": 404
}
```

## Contributing

This project has adopted the [Microsoft Open Source Code of Conduct](https://opensource.microsoft.com/codeofconduct/). For more information see the [Code of Conduct FAQ](https://opensource.microsoft.com/codeofconduct/faq/) or contact [opencode@microsoft.com](mailto:opencode@microsoft.com) with any additional questions or comments.
