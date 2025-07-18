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

use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Is raised when a request to Weaviate fails due to insufficient permissions.
 *
 * This exception is thrown when the server returns a 403 Forbidden status code,
 * indicating that the authenticated user does not have permission to perform
 * the requested operation.
 *
 * Common causes:
 * - Invalid or expired API key
 * - API key lacks required permissions
 * - RBAC restrictions
 * - Resource-specific access controls
 *
 * @example Handling permission errors
 * ```php
 * try {
 *     $client->collections()->delete('ProtectedCollection');
 * } catch (InsufficientPermissionsException $e) {
 *     echo "Permission denied: " . $e->getMessage();
 *
 *     // Check if we have auth info
 *     $context = $e->getContext();
 *     if (isset($context['auth_type'])) {
 *         echo "Authentication type: " . $context['auth_type'];
 *     }
 *
 *     // Suggest solutions
 *     echo "Please check your API key permissions or contact your administrator.";
 * }
 * ```
 */
class InsufficientPermissionsException extends UnexpectedStatusCodeException
{
    /**
     * @param string $message Error message
     * @param array<string, mixed>|null $response Response data
     * @param array<string, mixed> $context Additional context
     * @param Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message = 'Insufficient permissions to perform this operation',
        ?array $response = null,
        array $context = [],
        ?Throwable $previous = null
    ) {
        // Add helpful context about permissions
        $context['error_type'] = 'insufficient_permissions';
        $context['suggestions'] = [
            'Check your API key is valid and not expired',
            'Verify your API key has the required permissions',
            'Contact your Weaviate administrator for access',
            'Check if RBAC policies are blocking this operation'
        ];

        parent::__construct($message, 403, $response, $context, $previous);
    }

    /**
     * Create from PSR-7 response
     *
     * @param string $message Error message
     * @param ResponseInterface $response PSR-7 response
     * @param array<string, mixed> $context Additional context
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function fromResponse(
        string $message,
        ResponseInterface $response,
        array $context = [],
        ?Throwable $previous = null
    ): self {
        $body = (string) $response->getBody();
        $decodedBody = null;

        if (!empty($body)) {
            $decoded = json_decode($body, true);
            $decodedBody = $decoded !== null ? $decoded : $body;
        }

        $responseData = [
            'status_code' => 403,
            'headers' => $response->getHeaders(),
            'body' => $decodedBody,
            'raw_body' => $body,
        ];

        // Extract more specific error message from response if available
        $message = 'Insufficient permissions to perform this operation';
        if (is_array($decodedBody) && isset($decodedBody['error'])) {
            $message = $decodedBody['error'];
        } elseif (is_string($decodedBody) && !empty($decodedBody)) {
            $message = $decodedBody;
        }

        return new self($message, $responseData, $context, $previous);
    }

    /**
     * Create for authentication failure
     *
     * @param string $authType Type of authentication that failed
     * @param array<string, mixed> $context Additional context
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function forAuthenticationFailure(
        string $authType = 'unknown',
        array $context = [],
        ?Throwable $previous = null
    ): self {
        $context['auth_type'] = $authType;
        $context['error_subtype'] = 'authentication_failure';

        $message = "Authentication failed using {$authType}. Please check your credentials.";

        return new self($message, null, $context, $previous);
    }

    /**
     * Create for RBAC restriction
     *
     * @param string $operation The operation that was blocked
     * @param string $resource The resource that was accessed
     * @param array<string, mixed> $context Additional context
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function forRbacRestriction(
        string $operation,
        string $resource,
        array $context = [],
        ?Throwable $previous = null
    ): self {
        $context['operation'] = $operation;
        $context['resource'] = $resource;
        $context['error_subtype'] = 'rbac_restriction';

        $message = "RBAC policy prevents {$operation} operation on {$resource}";

        return new self($message, null, $context, $previous);
    }
}
