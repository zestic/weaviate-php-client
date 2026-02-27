<?php

declare(strict_types=1);

/*
 * Copyright 2025-2026 Zestic
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Weaviate\Tests\Integration\Factory;

use Weaviate\Factory\WeaviateClientFactory;
use Weaviate\Tests\TestCase;

class WeaviateClientIntegrationTest extends TestCase
{
    public function testConnectToLocalEndToEndWorkflow(): void
    {
        $this->skipIfWeaviateNotAvailable();

        // Extract host and port from the Weaviate URL
        $url = parse_url($this->getWeaviateUrl());
        if ($url === false || !isset($url['host'], $url['port'])) {
            $this->fail('Invalid Weaviate URL');
        }
        $host = $url['host'] . ':' . $url['port'];

        $client = WeaviateClientFactory::connectToLocal($host);

        $testCollectionName = 'TestConnectToLocal_' . uniqid();

        try {
            // Ensure collection doesn't exist
            $this->assertFalse($client->collections()->exists($testCollectionName));

            // Create collection
            $result = $client->collections()->create($testCollectionName, [
                'properties' => [
                    ['name' => 'name', 'dataType' => ['text']],
                ],
            ]);

            $this->assertIsArray($result);
            $this->assertEquals($testCollectionName, $result['class']);

            // Verify collection exists
            $this->assertTrue($client->collections()->exists($testCollectionName));
        } finally {
            // Clean up - delete the test collection
            if ($client->collections()->exists($testCollectionName)) {
                $client->collections()->delete($testCollectionName);
            }
        }
    }
}
