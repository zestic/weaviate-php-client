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

namespace Weaviate\Schema;

use Weaviate\Connection\ConnectionInterface;

/**
 * Schema management API
 */
class Schema
{
    public function __construct(
        private readonly ConnectionInterface $connection
    ) {
    }

    /**
     * Get the complete schema
     *
     * @return array<string, mixed> Schema data
     */
    public function get(): array
    {
        return $this->connection->get('/v1/schema');
    }
}
