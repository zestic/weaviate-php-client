<?php

declare(strict_types=1);

/*
 * Copyright 2024 Zestic
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

namespace Weaviate\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case for all Weaviate tests
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Get the Weaviate URL for integration tests
     */
    protected function getWeaviateUrl(): string
    {
        return $_ENV['WEAVIATE_URL'] ?? 'http://localhost:8080';
    }

    /**
     * Get the Weaviate API key for integration tests
     */
    protected function getWeaviateApiKey(): string
    {
        return $_ENV['WEAVIATE_API_KEY'] ?? '';
    }

    /**
     * Skip test if Weaviate is not available
     */
    protected function skipIfWeaviateNotAvailable(): void
    {
        $url = $this->getWeaviateUrl();
        $context = stream_context_create([
            'http' => [
                'timeout' => 2,
                'ignore_errors' => true,
            ],
        ]);

        $result = @file_get_contents($url . '/v1/.well-known/ready', false, $context);

        if ($result === false) {
            $this->markTestSkipped('Weaviate is not available at ' . $url);
        }
    }
}
