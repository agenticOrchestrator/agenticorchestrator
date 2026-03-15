<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Unit\StructuredOutput;

use AgenticOrchestrator\StructuredOutput\SchemaBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchemaBuilder::class)]
class SchemaBuilderTest extends TestCase
{
    #[Test]
    public function it_creates_object_schema(): void
    {
        $schema = SchemaBuilder::object()->build();

        $this->assertSame('object', $schema['type']);
    }

    #[Test]
    public function it_creates_array_schema(): void
    {
        $schema = SchemaBuilder::array()
            ->items(SchemaBuilder::string()->build())
            ->build();

        $this->assertSame('array', $schema['type']);
        $this->assertSame('string', $schema['items']['type']);
    }

    #[Test]
    public function it_adds_string_property(): void
    {
        $schema = SchemaBuilder::object()
            ->stringProperty('name', 'The user name', required: true)
            ->build();

        $this->assertArrayHasKey('name', $schema['properties']);
        $this->assertSame('string', $schema['properties']['name']['type']);
        $this->assertSame('The user name', $schema['properties']['name']['description']);
        $this->assertContains('name', $schema['required']);
    }

    #[Test]
    public function it_adds_number_property(): void
    {
        $schema = SchemaBuilder::object()
            ->numberProperty('price', minimum: 0.0, maximum: 1000.0)
            ->build();

        $this->assertSame('number', $schema['properties']['price']['type']);
        $this->assertSame(0.0, $schema['properties']['price']['minimum']);
        $this->assertSame(1000.0, $schema['properties']['price']['maximum']);
    }

    #[Test]
    public function it_adds_enum_property(): void
    {
        $schema = SchemaBuilder::object()
            ->enumProperty('status', ['active', 'inactive', 'pending'], required: true)
            ->build();

        $this->assertSame(['active', 'inactive', 'pending'], $schema['properties']['status']['enum']);
    }

    #[Test]
    public function it_adds_array_property(): void
    {
        $schema = SchemaBuilder::object()
            ->arrayProperty(
                'tags',
                SchemaBuilder::string(),
                minItems: 1,
                maxItems: 10,
            )
            ->build();

        $this->assertSame('array', $schema['properties']['tags']['type']);
        $this->assertSame('string', $schema['properties']['tags']['items']['type']);
        $this->assertSame(1, $schema['properties']['tags']['minItems']);
        $this->assertSame(10, $schema['properties']['tags']['maxItems']);
    }

    #[Test]
    public function it_adds_nested_object_property(): void
    {
        $addressSchema = SchemaBuilder::object()
            ->stringProperty('street', required: true)
            ->stringProperty('city', required: true);

        $schema = SchemaBuilder::object()
            ->objectProperty('address', $addressSchema)
            ->build();

        $this->assertSame('object', $schema['properties']['address']['type']);
        $this->assertArrayHasKey('street', $schema['properties']['address']['properties']);
    }

    #[Test]
    public function it_sets_title_and_description(): void
    {
        $schema = SchemaBuilder::object()
            ->title('User')
            ->description('A user object')
            ->build();

        $this->assertSame('User', $schema['title']);
        $this->assertSame('A user object', $schema['description']);
    }

    #[Test]
    public function it_marks_properties_as_required(): void
    {
        $schema = SchemaBuilder::object()
            ->stringProperty('name')
            ->stringProperty('email')
            ->required(['name', 'email'])
            ->build();

        $this->assertContains('name', $schema['required']);
        $this->assertContains('email', $schema['required']);
    }

    #[Test]
    public function it_makes_schema_strict(): void
    {
        $schema = SchemaBuilder::object()
            ->strict()
            ->build();

        $this->assertFalse($schema['additionalProperties']);
    }

    #[Test]
    public function it_converts_to_json(): void
    {
        $schema = SchemaBuilder::object()
            ->stringProperty('name', required: true);

        $json = $schema->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertSame('object', $decoded['type']);
    }

    #[Test]
    public function it_creates_from_existing_array(): void
    {
        $existing = [
            'type' => 'object',
            'properties' => ['name' => ['type' => 'string']],
            'required' => ['name'],
        ];

        $schema = SchemaBuilder::from($existing)
            ->stringProperty('email')
            ->build();

        $this->assertArrayHasKey('name', $schema['properties']);
        $this->assertArrayHasKey('email', $schema['properties']);
    }

    #[Test]
    public function it_adds_string_property_with_pattern(): void
    {
        $schema = SchemaBuilder::object()
            ->stringProperty('email', pattern: '^[a-z]+@[a-z]+\\.[a-z]+$')
            ->build();

        $this->assertSame('^[a-z]+@[a-z]+\\.[a-z]+$', $schema['properties']['email']['pattern']);
    }

    #[Test]
    public function it_adds_examples(): void
    {
        $schema = SchemaBuilder::object()
            ->examples([['name' => 'John'], ['name' => 'Jane']])
            ->build();

        $this->assertCount(2, $schema['examples']);
    }
}
