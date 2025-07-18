<?php

declare(strict_types=1);

/*
 * Copyright 2025 Zestic
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

namespace Weaviate\Connection;

/**
 * Interface for HTTP connections to Weaviate
 */
interface ConnectionInterface
{
    /**
     * Make a GET request
     *
     * @param string $path The API path
     * @param array<string, mixed> $params Query parameters
     * @return array<string, mixed> Response data
     */
    public function get(string $path, array $params = []): array;

    /**
     * Make a POST request
     *
     * @param string $path The API path
     * @param array<string, mixed> $data Request body data
     * @return array<string, mixed> Response data
     */
    public function post(string $path, array $data = []): array;

    /**
     * Make a PUT request
     *
     * @param string $path The API path
     * @param array<string, mixed> $data Request body data
     * @return array<string, mixed> Response data
     */
    public function put(string $path, array $data = []): array;

    /**
     * Make a PATCH request
     *
     * @param string $path The API path
     * @param array<string, mixed> $data Request body data
     * @return array<string, mixed> Response data
     */
    public function patch(string $path, array $data = []): array;

    /**
     * Make a DELETE request
     *
     * @param string $path The API path
     * @return bool Success status
     */
    public function delete(string $path): bool;

    /**
     * Make a DELETE request with data
     *
     * @param string $path The API path
     * @param array<string, mixed> $data Request body data
     * @return bool Success status
     */
    public function deleteWithData(string $path, array $data = []): bool;
}
