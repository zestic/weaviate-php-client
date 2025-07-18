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

namespace Weaviate\Tests\Unit\Schema;

use PHPUnit\Framework\TestCase;
use Weaviate\Connection\ConnectionInterface;
use Weaviate\Exceptions\NotFoundException;
use Weaviate\Schema\Schema;

class SchemaTest extends TestCase
{
    private ConnectionInterface $connection;
    private Schema $schema;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->schema = new Schema($this->connection);
    }

    public function testGetCompleteSchema(): void
    {
        $expectedSchema = [
            'classes' => [
                [
                    'class' => 'Article',
                    'properties' => [
                        ['name' => 'title', 'dataType' => ['text']]
                    ]
                ]
            ]
        ];

        $this->connection
            ->expects($this->once())
            ->method('get')
            ->with('/v1/schema')
            ->willReturn($expectedSchema);

        $result = $this->schema->get();

        $this->assertEquals($expectedSchema, $result);
    }

    public function testGetSpecificCollectionSchema(): void
    {
        $expectedSchema = [
            'class' => 'Article',
            'properties' => [
                ['name' => 'title', 'dataType' => ['text']]
            ]
        ];

        $this->connection
            ->expects($this->once())
            ->method('get')
            ->with('/v1/schema/Article')
            ->willReturn($expectedSchema);

        $result = $this->schema->get('Article');

        $this->assertEquals($expectedSchema, $result);
    }

    public function testExistsReturnsTrueWhenCollectionExists(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('get')
            ->with('/v1/schema/Article')
            ->willReturn(['class' => 'Article']);

        $result = $this->schema->exists('Article');

        $this->assertTrue($result);
    }

    public function testExistsReturnsFalseWhenCollectionDoesNotExist(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('get')
            ->with('/v1/schema/Article')
            ->willThrowException(new NotFoundException('Collection not found'));

        $result = $this->schema->exists('Article');

        $this->assertFalse($result);
    }

    public function testCreateCollection(): void
    {
        $classDefinition = [
            'class' => 'Article',
            'properties' => [
                ['name' => 'title', 'dataType' => ['text']]
            ]
        ];

        $expectedResponse = [
            'class' => 'Article',
            'properties' => [
                ['name' => 'title', 'dataType' => ['text']]
            ]
        ];

        $this->connection
            ->expects($this->once())
            ->method('post')
            ->with('/v1/schema', $classDefinition)
            ->willReturn($expectedResponse);

        $result = $this->schema->create($classDefinition);

        $this->assertEquals($expectedResponse, $result);
    }

    public function testCreateCollectionThrowsExceptionForInvalidDefinition(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Class definition must include a "class" field with string value');

        $this->schema->create(['properties' => []]);
    }

    public function testCreateCollectionThrowsExceptionForEmptyClassName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Class name cannot be empty');

        $this->schema->create(['class' => '']);
    }

    public function testCreateCollectionValidatesProperties(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Property definition must include a "name" field with string value');

        $this->schema->create([
            'class' => 'Article',
            'properties' => [
                ['dataType' => ['text']] // Missing name
            ]
        ]);
    }

    public function testUpdateCollection(): void
    {
        $updates = ['description' => 'Updated description'];
        $expectedResponse = [
            'class' => 'Article',
            'description' => 'Updated description'
        ];

        $this->connection
            ->expects($this->once())
            ->method('put')
            ->with('/v1/schema/Article', $updates);

        $this->connection
            ->expects($this->once())
            ->method('get')
            ->with('/v1/schema/Article')
            ->willReturn($expectedResponse);

        $result = $this->schema->update('Article', $updates);

        $this->assertEquals($expectedResponse, $result);
    }

    public function testDeleteCollection(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('delete')
            ->with('/v1/schema/Article')
            ->willReturn(true);

        $result = $this->schema->delete('Article');

        $this->assertTrue($result);
    }

    public function testAddProperty(): void
    {
        $property = ['name' => 'author', 'dataType' => ['text']];
        $expectedResponse = [
            'class' => 'Article',
            'properties' => [
                ['name' => 'title', 'dataType' => ['text']],
                ['name' => 'author', 'dataType' => ['text']]
            ]
        ];

        $this->connection
            ->expects($this->once())
            ->method('post')
            ->with('/v1/schema/Article/properties', $property);

        $this->connection
            ->expects($this->once())
            ->method('get')
            ->with('/v1/schema/Article')
            ->willReturn($expectedResponse);

        $result = $this->schema->addProperty('Article', $property);

        $this->assertEquals($expectedResponse, $result);
    }

    public function testAddPropertyThrowsExceptionForInvalidProperty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Property definition must include a "name" field with string value');

        $this->schema->addProperty('Article', ['dataType' => ['text']]);
    }

    public function testUpdateProperty(): void
    {
        $updates = ['description' => 'Updated property description'];
        $expectedResponse = [
            'class' => 'Article',
            'properties' => [
                ['name' => 'title', 'dataType' => ['text'], 'description' => 'Updated property description']
            ]
        ];

        $this->connection
            ->expects($this->once())
            ->method('put')
            ->with('/v1/schema/Article/properties/title', $updates);

        $this->connection
            ->expects($this->once())
            ->method('get')
            ->with('/v1/schema/Article')
            ->willReturn($expectedResponse);

        $result = $this->schema->updateProperty('Article', 'title', $updates);

        $this->assertEquals($expectedResponse, $result);
    }

    public function testDeleteProperty(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('delete')
            ->with('/v1/schema/Article/properties/title')
            ->willReturn(true);

        $result = $this->schema->deleteProperty('Article', 'title');

        $this->assertTrue($result);
    }

    public function testGetProperty(): void
    {
        $schema = [
            'class' => 'Article',
            'properties' => [
                ['name' => 'title', 'dataType' => ['text']],
                ['name' => 'author', 'dataType' => ['text']]
            ]
        ];

        $this->connection
            ->expects($this->once())
            ->method('get')
            ->with('/v1/schema/Article')
            ->willReturn($schema);

        $result = $this->schema->getProperty('Article', 'title');

        $this->assertEquals(['name' => 'title', 'dataType' => ['text']], $result);
    }

    public function testGetPropertyThrowsExceptionWhenPropertyNotFound(): void
    {
        $schema = [
            'class' => 'Article',
            'properties' => [
                ['name' => 'title', 'dataType' => ['text']]
            ]
        ];

        $this->connection
            ->expects($this->once())
            ->method('get')
            ->with('/v1/schema/Article')
            ->willReturn($schema);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Property 'author' not found in collection 'Article'");

        $this->schema->getProperty('Article', 'author');
    }

    public function testValidatePropertyDefinitionWithValidDataTypes(): void
    {
        $validProperties = [
            ['name' => 'text_prop', 'dataType' => ['text']],
            ['name' => 'string_prop', 'dataType' => ['string']],
            ['name' => 'int_prop', 'dataType' => ['int']],
            ['name' => 'number_prop', 'dataType' => ['number']],
            ['name' => 'boolean_prop', 'dataType' => ['boolean']],
            ['name' => 'date_prop', 'dataType' => ['date']],
            ['name' => 'uuid_prop', 'dataType' => ['uuid']],
            ['name' => 'geo_prop', 'dataType' => ['geoCoordinates']],
            ['name' => 'phone_prop', 'dataType' => ['phoneNumber']],
            ['name' => 'blob_prop', 'dataType' => ['blob']],
            ['name' => 'object_prop', 'dataType' => ['object']],
            ['name' => 'text_array_prop', 'dataType' => ['text[]']],
            ['name' => 'reference_prop', 'dataType' => ['Article']], // Reference to another class
        ];

        $this->connection
            ->expects($this->exactly(count($validProperties)))
            ->method('post')
            ->with('/v1/schema', $this->callback(function ($classDefinition) {
                return isset($classDefinition['class']) && $classDefinition['class'] === 'TestClass';
            }))
            ->willReturn(['class' => 'TestClass', 'properties' => []]);

        foreach ($validProperties as $property) {
            $classDefinition = [
                'class' => 'TestClass',
                'properties' => [$property]
            ];

            // Should not throw exception
            $this->schema->create($classDefinition);
        }
    }

    public function testValidatePropertyDefinitionThrowsExceptionForInvalidDataType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid data type: invalid_type');

        $this->schema->create([
            'class' => 'TestClass',
            'properties' => [
                ['name' => 'test_prop', 'dataType' => ['invalid_type']]
            ]
        ]);
    }
}
