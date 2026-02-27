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

namespace Weaviate\Tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Weaviate\Connection\ConnectionInterface;
use Weaviate\Data\DataOperations;

class CrossReferenceTest extends TestCase
{
    private ConnectionInterface&MockObject $connection;
    private DataOperations $dataOperations;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->dataOperations = new DataOperations($this->connection, 'TestClass');
    }

    public function testCanAddCrossReference(): void
    {
        $fromUuid = '123e4567-e89b-12d3-a456-426614174000';
        $fromProperty = 'hasCategory';
        $to = '987fcdeb-51a2-43d1-9f12-345678901234';

        $this->connection
            ->expects($this->once())
            ->method('post')
            ->with(
                '/v1/objects/TestClass/123e4567-e89b-12d3-a456-426614174000/references/hasCategory',
                ['beacon' => 'weaviate://localhost/987fcdeb-51a2-43d1-9f12-345678901234']
            );

        $result = $this->dataOperations->referenceAdd($fromUuid, $fromProperty, $to);

        $this->assertTrue($result);
    }

    public function testCanAddCrossReferenceWithTenant(): void
    {
        $dataOperations = new DataOperations($this->connection, 'TestClass', 'tenant-123');
        $fromUuid = '123e4567-e89b-12d3-a456-426614174000';
        $fromProperty = 'hasCategory';
        $to = '987fcdeb-51a2-43d1-9f12-345678901234';

        $this->connection
            ->expects($this->once())
            ->method('post')
            ->with(
                '/v1/objects/TestClass/123e4567-e89b-12d3-a456-426614174000/references/hasCategory?tenant=tenant-123',
                ['beacon' => 'weaviate://localhost/987fcdeb-51a2-43d1-9f12-345678901234']
            );

        $result = $dataOperations->referenceAdd($fromUuid, $fromProperty, $to);

        $this->assertTrue($result);
    }

    public function testCanDeleteCrossReference(): void
    {
        $fromUuid = '123e4567-e89b-12d3-a456-426614174000';
        $fromProperty = 'hasCategory';
        $to = '987fcdeb-51a2-43d1-9f12-345678901234';

        $this->connection
            ->expects($this->once())
            ->method('deleteWithData')
            ->with(
                '/v1/objects/TestClass/123e4567-e89b-12d3-a456-426614174000/references/hasCategory',
                ['beacon' => 'weaviate://localhost/987fcdeb-51a2-43d1-9f12-345678901234']
            );

        $result = $this->dataOperations->referenceDelete($fromUuid, $fromProperty, $to);

        $this->assertTrue($result);
    }

    public function testCanReplaceCrossReferenceWithSingleTarget(): void
    {
        $fromUuid = '123e4567-e89b-12d3-a456-426614174000';
        $fromProperty = 'hasCategory';
        $to = '987fcdeb-51a2-43d1-9f12-345678901234';

        $this->connection
            ->expects($this->once())
            ->method('put')
            ->with(
                '/v1/objects/TestClass/123e4567-e89b-12d3-a456-426614174000/references/hasCategory',
                ['weaviate://localhost/987fcdeb-51a2-43d1-9f12-345678901234']
            );

        $result = $this->dataOperations->referenceReplace($fromUuid, $fromProperty, $to);

        $this->assertTrue($result);
    }

    public function testCanReplaceCrossReferenceWithMultipleTargets(): void
    {
        $fromUuid = '123e4567-e89b-12d3-a456-426614174000';
        $fromProperty = 'hasCategories';
        $to = [
            '987fcdeb-51a2-43d1-9f12-345678901234',
            '456e7890-a12b-34c5-d678-901234567890'
        ];

        $this->connection
            ->expects($this->once())
            ->method('put')
            ->with(
                '/v1/objects/TestClass/123e4567-e89b-12d3-a456-426614174000/references/hasCategories',
                [
                    'weaviate://localhost/987fcdeb-51a2-43d1-9f12-345678901234',
                    'weaviate://localhost/456e7890-a12b-34c5-d678-901234567890'
                ]
            );

        $result = $this->dataOperations->referenceReplace($fromUuid, $fromProperty, $to);

        $this->assertTrue($result);
    }

    public function testCanAddMultipleCrossReferences(): void
    {
        $references = [
            [
                'fromUuid' => '123e4567-e89b-12d3-a456-426614174000',
                'fromProperty' => 'hasCategory',
                'to' => '987fcdeb-51a2-43d1-9f12-345678901234'
            ],
            [
                'fromUuid' => '456e7890-a12b-34c5-d678-901234567890',
                'fromProperty' => 'hasAuthor',
                'to' => 'abc12345-def6-7890-abcd-ef1234567890'
            ]
        ];

        $this->connection
            ->expects($this->exactly(2))
            ->method('post');

        $results = $this->dataOperations->referenceAddMany($references);

        $this->assertCount(2, $results);
        $this->assertArrayHasKey('123e4567-e89b-12d3-a456-426614174000.hasCategory', $results);
        $this->assertArrayHasKey('456e7890-a12b-34c5-d678-901234567890.hasAuthor', $results);
    }

    public function testReferenceAddReturnsFalseOnException(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('post')
            ->willThrowException(new \Exception('Connection failed'));

        $result = $this->dataOperations->referenceAdd('uuid', 'property', 'target');

        $this->assertFalse($result);
    }

    public function testReferenceDeleteReturnsFalseOnException(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('deleteWithData')
            ->willThrowException(new \Exception('Connection failed'));

        $result = $this->dataOperations->referenceDelete('uuid', 'property', 'target');

        $this->assertFalse($result);
    }

    public function testReferenceReplaceReturnsFalseOnException(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('put')
            ->willThrowException(new \Exception('Connection failed'));

        $result = $this->dataOperations->referenceReplace('uuid', 'property', 'target');

        $this->assertFalse($result);
    }
}
