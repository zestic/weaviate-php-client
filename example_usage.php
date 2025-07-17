<?php

require_once 'vendor/autoload.php';

use Weaviate\WeaviateClient;
use Weaviate\Connection\HttpConnection;
use Weaviate\Auth\ApiKey;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

// Create HTTP client and factories
$httpClient = new Client();
$httpFactory = new HttpFactory();

// Create connection (with optional authentication)
$connection = new HttpConnection(
    'http://localhost:8080',  // Weaviate URL
    $httpClient,
    $httpFactory,
    $httpFactory,
    // new ApiKey('your-api-key')  // Uncomment if authentication is needed
);

// Create Weaviate client
$client = new WeaviateClient($connection);

try {
    // Example 1: Check if a collection exists
    echo "Checking if 'Organization' collection exists...\n";
    $exists = $client->collections()->exists('Organization');
    echo $exists ? "Collection exists!\n" : "Collection does not exist.\n";

    // Example 2: Create a collection with multi-tenancy
    if (!$exists) {
        echo "Creating 'Organization' collection...\n";
        $result = $client->collections()->create('Organization', [
            'properties' => [
                ['name' => 'name', 'dataType' => ['text']],
                ['name' => 'createdAt', 'dataType' => ['date']]
            ],
            'multiTenancyConfig' => ['enabled' => true]
        ]);
        echo "Collection created: " . $result['class'] . "\n";
    }

    // Example 3: Create a tenant
    echo "Creating tenant...\n";
    $client->collections()->get('Organization')
        ->tenants()
        ->create([['name' => 'example-tenant']]);

    // Example 4: Create an object with tenant
    echo "Creating object...\n";
    $orgId = '123e4567-e89b-12d3-a456-426614174000';
    $result = $client->collections()->get('Organization')
        ->withTenant('example-tenant')
        ->data()
        ->create([
            'id' => $orgId,
            'name' => 'Example Organization',
            'createdAt' => '2024-01-01T00:00:00Z'
        ]);
    echo "Object created with ID: " . $result['id'] . "\n";

    // Example 5: Retrieve the object
    echo "Retrieving object...\n";
    $retrieved = $client->collections()->get('Organization')
        ->withTenant('example-tenant')
        ->data()
        ->get($orgId);
    echo "Retrieved object: " . $retrieved['properties']['name'] . "\n";

    // Example 6: Update the object
    echo "Updating object...\n";
    $updated = $client->collections()->get('Organization')
        ->withTenant('example-tenant')
        ->data()
        ->update($orgId, [
            'name' => 'Updated Organization'
        ]);
    echo "Updated object: " . $updated['properties']['name'] . "\n";

    // Example 7: Delete the object
    echo "Deleting object...\n";
    $deleted = $client->collections()->get('Organization')
        ->withTenant('example-tenant')
        ->data()
        ->delete($orgId);
    echo $deleted ? "Object deleted successfully!\n" : "Failed to delete object.\n";

    // Clean up: Delete the collection
    echo "Cleaning up collection...\n";
    $client->collections()->delete('Organization');
    echo "Collection deleted.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Make sure Weaviate is running at http://localhost:8080\n";
}
