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

namespace Weaviate;

use Weaviate\Auth\AuthInterface;
use Weaviate\Collections\Collections;
use Weaviate\Connection\ConnectionInterface;
use Weaviate\Schema\Schema;

/**
 * Main Weaviate client class
 */
class WeaviateClient
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly ?AuthInterface $auth = null
    ) {
    }

    /**
     * Get the authentication instance
     */
    public function getAuth(): ?AuthInterface
    {
        return $this->auth;
    }

    /**
     * Get collections API
     */
    public function collections(): Collections
    {
        return new Collections($this->connection);
    }

    /**
     * Get schema API
     */
    public function schema(): Schema
    {
        return new Schema($this->connection);
    }
}
