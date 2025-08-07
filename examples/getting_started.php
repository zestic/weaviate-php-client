<?php

declare(strict_types=1);

/*
 * Getting Started Example for Weaviate PHP Client
 *
 * This comprehensive example demonstrates the core features of the Weaviate PHP
 * client including connection methods, multi-tenancy, and basic CRUD operations.
 *
 * Run this example to see the client in action:
 * php examples/getting_started.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Weaviate\WeaviateClient;

/**
 * Getting Started Example
 *
 * This comprehensive example demonstrates the core features of the Weaviate PHP
 * client including connection methods, multi-tenancy, and basic CRUD operations.
 *
 * Run this example to see the client in action:
 * php examples/getting_started.php
 */

echo "=== Weaviate PHP Client - Getting Started Example ===\n\n";

// Easy way: Connect to local Weaviate instance
$client = WeaviateClient::connectToLocal();

// Connect to Docker container on custom port
// $client = WeaviateClient::connectToLocal('localhost:18080');

// Connect with authentication
// $client = WeaviateClient::connectToLocal(
//     'localhost:8080',
//     new ApiKey('your-api-key')
// );

// Connect to Weaviate Cloud
// $client = WeaviateClient::connectToWeaviateCloud(
//     'my-cluster.weaviate.network',
//     new ApiKey('your-wcd-api-key')
// );

// Connect to custom Weaviate instance
// $client = WeaviateClient::connectToCustom(
//     'my-server.com',
//     9200,
//     true,
//     new ApiKey('api-key'),
//     ['X-Custom-Header' => 'value']
// );

// Advanced way: Manual connection setup
/*
use Weaviate\Connection\HttpConnection;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

$httpClient = new Client();
$httpFactory = new HttpFactory();

$connection = new HttpConnection(
    'http://localhost:8080',
    $httpClient,
    $httpFactory,
    $httpFactory,
    new ApiKey('your-api-key')  // Optional authentication
);

$client = new WeaviateClient($connection);
*/

try {
    // Example 1: Check if a collection exists
    echo "Checking if 'Organization' collection exists...\n";
    $exists = $client->collections()->exists('Organization');
    echo $exists ? "Collection exists!\n" : "Collection does not exist.\n";

    // Example 2: Create a collection with multi-tenancy
    if (!$exists) {
        echo "Creating 'Organization' collection...\n";
        $result = $client->collections()->create(
            'Organization',
            [
                'properties' => [
                    ['name' => 'name', 'dataType' => ['text']],
                    ['name' => 'createdAt', 'dataType' => ['date']]
                ],
                'multiTenancyConfig' => ['enabled' => true]
            ]
        );
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
        ->create(
            [
                'id' => $orgId,
                'name' => 'Example Organization',
                'createdAt' => '2024-01-01T00:00:00Z'
            ]
        );
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
        ->update(
            $orgId,
            [
                'name' => 'Updated Organization'
            ]
        );
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
