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

namespace Weaviate\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use Weaviate\Auth\ApiKey;
use Psr\Http\Message\RequestInterface;

class ApiKeyTest extends TestCase
{
    public function testCanCreateApiKeyAuth(): void
    {
        $auth = new ApiKey('my-secret-key');

        $this->assertInstanceOf(ApiKey::class, $auth);
    }

    public function testAppliesAuthorizationHeader(): void
    {
        $auth = new ApiKey('my-secret-key');
        $request = $this->createMock(RequestInterface::class);

        $request->expects($this->once())
            ->method('withHeader')
            ->with('Authorization', 'Bearer my-secret-key')
            ->willReturnSelf();

        $auth->apply($request);
    }
}
