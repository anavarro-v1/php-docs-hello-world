<?php

namespace App\Config;

/**
 * Azure Storage Configuration
 */
class AzureStorageConfig
{
    /**
     * Get Azure Storage connection string from environment variables
     * Format: DefaultEndpointsProtocol=https;AccountName=<account_name>;AccountKey=<account_key>;EndpointSuffix=core.windows.net
     */
    public static function getConnectionString(): string
    {
        $connectionString = getenv('AZURE_STORAGE_CONNECTION_STRING');
        
        if (empty($connectionString)) {
            throw new \Exception('AZURE_STORAGE_CONNECTION_STRING environment variable is not set');
        }
        
        return $connectionString;
    }
    
    /**
     * Get the default container name for blob storage
     */
    public static function getDefaultContainer(): string
    {
        return getenv('AZURE_STORAGE_CONTAINER') ?: 'documents';
    }
    
    /**
     * Get Azure Storage account name from connection string
     */
    public static function getAccountName(): string
    {
        $connectionString = self::getConnectionString();
        preg_match('/AccountName=([^;]+)/', $connectionString, $matches);
        
        if (empty($matches[1])) {
            throw new \Exception('Could not extract account name from connection string');
        }
        
        return $matches[1];
    }
}
