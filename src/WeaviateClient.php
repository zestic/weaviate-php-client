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

namespace Weaviate;

use Weaviate\Auth\AuthInterface;
use Weaviate\Collections\Collections;
use Weaviate\Connection\ConnectionInterface;
use Weaviate\Schema\Schema;

class WeaviateClient implements WeaviateClientInterface
{
    private readonly Collections $collections;
    private readonly ?AuthInterface $auth;

    public function __construct(
        private readonly ConnectionInterface $connection,
        Collections|AuthInterface|null $collectionsOrAuth = null,
        ?AuthInterface $auth = null,
    ) {
        if ($collectionsOrAuth instanceof Collections) {
            $this->collections = $collectionsOrAuth;
            $this->auth = $auth;
            return;
        }

        $this->collections = new Collections($connection);
        $this->auth = $collectionsOrAuth instanceof AuthInterface ? $collectionsOrAuth : $auth;
    }

    public function connect(): void
    {
        throw new \Exception('Not implemented');
    }

    public function close(): void
    {
        throw new \Exception('Not implemented');
    }

    public function isConnected(): bool
    {
        throw new \Exception('Not implemented');
    }

    public function isLive(): bool
    {
        throw new \Exception('Not implemented');
    }

    public function isReady(): bool
    {
        throw new \Exception('Not implemented');
    }

    public function getCollections(): Collections
    {
        return $this->collections;
    }

    public function collections(): Collections
    {
        return $this->getCollections();
    }

    public function getAuth(): ?AuthInterface
    {
        return $this->auth;
    }

    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    public function schema(): Schema
    {
        return new Schema($this->connection);
    }
}
