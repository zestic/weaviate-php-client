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

namespace Weaviate\Exceptions;

/**
 * Exception thrown when a resource is not found (404 errors).
 *
 * This exception is thrown when attempting to access a resource that
 * does not exist, such as:
 * - Non-existent collections
 * - Non-existent objects
 * - Non-existent tenants
 * - Non-existent properties
 *
 * @example Handling not found errors
 * ```php
 * try {
 *     $object = $collection->data()->get('non-existent-id');
 * } catch (NotFoundException $e) {
 *     echo "Resource not found: " . $e->getMessage();
 *
 *     $context = $e->getContext();
 *     if (isset($context['resource_type'])) {
 *         echo "Resource type: " . $context['resource_type'];
 *     }
 * }
 * ```
 */
class NotFoundException extends UnexpectedStatusCodeException
{
    /**
     * @param string $message Error message
     * @param string|null $resourceType Type of resource that wasn't found
     * @param string|null $resourceId ID of the resource that wasn't found
     * @param array<string, mixed> $context Additional context
     */
    public function __construct(
        string $message = 'Resource not found',
        ?string $resourceType = null,
        ?string $resourceId = null,
        array $context = []
    ) {
        if ($resourceType !== null) {
            $context['resource_type'] = $resourceType;
        }

        if ($resourceId !== null) {
            $context['resource_id'] = $resourceId;
        }

        parent::__construct($message, 404, null, $context);
    }

    /**
     * Create for collection not found
     *
     * @param string $collectionName Name of the collection
     * @return self
     */
    public static function forCollection(string $collectionName): self
    {
        return new self(
            "Collection '{$collectionName}' not found",
            'collection',
            $collectionName
        );
    }

    /**
     * Create for object not found
     *
     * @param string $objectId ID of the object
     * @param string|null $collectionName Name of the collection
     * @return self
     */
    public static function forObject(string $objectId, ?string $collectionName = null): self
    {
        $message = "Object '{$objectId}' not found";
        if ($collectionName !== null) {
            $message .= " in collection '{$collectionName}'";
        }

        $context = $collectionName !== null ? ['collection' => $collectionName] : [];

        return new self($message, 'object', $objectId, $context);
    }

    /**
     * Create for tenant not found
     *
     * @param string $tenantName Name of the tenant
     * @param string|null $collectionName Name of the collection
     * @return self
     */
    public static function forTenant(string $tenantName, ?string $collectionName = null): self
    {
        $message = "Tenant '{$tenantName}' not found";
        if ($collectionName !== null) {
            $message .= " in collection '{$collectionName}'";
        }

        $context = $collectionName !== null ? ['collection' => $collectionName] : [];

        return new self($message, 'tenant', $tenantName, $context);
    }

    /**
     * Create from PSR-7 response
     *
     * @param string $message Error message
     * @param \Psr\Http\Message\ResponseInterface $response PSR-7 response
     * @param array<string, mixed> $context Additional context
     * @param \Throwable|null $previous Previous exception
     * @return self
     */
    public static function fromResponse(
        string $message,
        \Psr\Http\Message\ResponseInterface $response,
        array $context = [],
        ?\Throwable $previous = null
    ): self {
        $body = (string) $response->getBody();
        $decodedBody = null;

        if (!empty($body)) {
            $decoded = json_decode($body, true);
            $decodedBody = $decoded !== null ? $decoded : $body;
        }

        // Extract more specific error message from response if available
        $message = 'Resource not found';
        if (is_array($decodedBody) && isset($decodedBody['error'])) {
            $message = $decodedBody['error'];
        } elseif (is_string($decodedBody) && !empty($decodedBody)) {
            $message = $decodedBody;
        }

        return new self($message, null, null, $context);
    }
}
