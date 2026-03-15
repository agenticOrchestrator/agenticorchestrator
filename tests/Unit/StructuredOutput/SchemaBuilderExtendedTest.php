<?php

declare(strict_types=1);

use AgenticOrchestrator\StructuredOutput\SchemaBuilder;

covers(SchemaBuilder::class);

describe('SchemaBuilder - extended coverage', function () {

    describe('static factory methods', function () {
        it('creates a string schema', function () {
            $schema = SchemaBuilder::string()->build();

            expect($schema['type'])->toBe('string');
        });

        it('creates a number schema', function () {
            $schema = SchemaBuilder::number()->build();

            expect($schema['type'])->toBe('number');
        });

        it('creates an integer schema', function () {
            $schema = SchemaBuilder::integer()->build();

            expect($schema['type'])->toBe('integer');
        });

        it('creates a boolean schema', function () {
            $schema = SchemaBuilder::boolean()->build();

            expect($schema['type'])->toBe('boolean');
        });
    });

    describe('integer property', function () {
        it('adds integer property with all options', function () {
            $schema = SchemaBuilder::object()
                ->integerProperty('age', 'User age', required: true, minimum: 0, maximum: 150)
                ->build();

            $prop = $schema['properties']['age'];
            expect($prop['type'])->toBe('integer')
                ->and($prop['description'])->toBe('User age')
                ->and($prop['minimum'])->toBe(0)
                ->and($prop['maximum'])->toBe(150)
                ->and($schema['required'])->toContain('age');
        });

        it('adds integer property with no optional args', function () {
            $schema = SchemaBuilder::object()
                ->integerProperty('count')
                ->build();

            $prop = $schema['properties']['count'];
            expect($prop)->toBe(['type' => 'integer']);
        });

        it('adds integer property with only minimum', function () {
            $schema = SchemaBuilder::object()
                ->integerProperty('score', minimum: 0)
                ->build();

            $prop = $schema['properties']['score'];
            expect($prop['minimum'])->toBe(0)
                ->and($prop)->not->toHaveKey('maximum');
        });

        it('adds integer property with only maximum', function () {
            $schema = SchemaBuilder::object()
                ->integerProperty('level', maximum: 100)
                ->build();

            $prop = $schema['properties']['level'];
            expect($prop['maximum'])->toBe(100)
                ->and($prop)->not->toHaveKey('minimum');
        });
    });

    describe('boolean property', function () {
        it('adds boolean property with description', function () {
            $schema = SchemaBuilder::object()
                ->booleanProperty('active', 'Is active', required: true)
                ->build();

            $prop = $schema['properties']['active'];
            expect($prop['type'])->toBe('boolean')
                ->and($prop['description'])->toBe('Is active')
                ->and($schema['required'])->toContain('active');
        });

        it('adds boolean property without description', function () {
            $schema = SchemaBuilder::object()
                ->booleanProperty('flag')
                ->build();

            $prop = $schema['properties']['flag'];
            expect($prop)->toBe(['type' => 'boolean']);
        });
    });

    describe('string property advanced options', function () {
        it('adds string property with minLength and maxLength', function () {
            $schema = SchemaBuilder::object()
                ->stringProperty('username', minLength: 3, maxLength: 50)
                ->build();

            $prop = $schema['properties']['username'];
            expect($prop['minLength'])->toBe(3)
                ->and($prop['maxLength'])->toBe(50);
        });

        it('adds string property with enum', function () {
            $schema = SchemaBuilder::object()
                ->stringProperty('color', enum: ['red', 'green', 'blue'])
                ->build();

            expect($schema['properties']['color']['enum'])->toBe(['red', 'green', 'blue']);
        });

        it('adds string property with only minLength', function () {
            $schema = SchemaBuilder::object()
                ->stringProperty('name', minLength: 1)
                ->build();

            $prop = $schema['properties']['name'];
            expect($prop['minLength'])->toBe(1)
                ->and($prop)->not->toHaveKey('maxLength');
        });

        it('adds string property with only maxLength', function () {
            $schema = SchemaBuilder::object()
                ->stringProperty('abbrev', maxLength: 5)
                ->build();

            $prop = $schema['properties']['abbrev'];
            expect($prop['maxLength'])->toBe(5)
                ->and($prop)->not->toHaveKey('minLength');
        });
    });

    describe('number property', function () {
        it('adds number property with description', function () {
            $schema = SchemaBuilder::object()
                ->numberProperty('price', 'The price')
                ->build();

            expect($schema['properties']['price']['description'])->toBe('The price');
        });

        it('adds number property without optional args', function () {
            $schema = SchemaBuilder::object()
                ->numberProperty('value')
                ->build();

            expect($schema['properties']['value'])->toBe(['type' => 'number']);
        });

        it('adds number property with only minimum', function () {
            $schema = SchemaBuilder::object()
                ->numberProperty('temp', minimum: -273.15)
                ->build();

            $prop = $schema['properties']['temp'];
            expect($prop['minimum'])->toBe(-273.15)
                ->and($prop)->not->toHaveKey('maximum');
        });

        it('adds number property with only maximum', function () {
            $schema = SchemaBuilder::object()
                ->numberProperty('percent', maximum: 100.0)
                ->build();

            $prop = $schema['properties']['percent'];
            expect($prop['maximum'])->toBe(100.0)
                ->and($prop)->not->toHaveKey('minimum');
        });
    });

    describe('array property advanced', function () {
        it('adds array property with description', function () {
            $schema = SchemaBuilder::object()
                ->arrayProperty('items', ['type' => 'string'], description: 'List of items')
                ->build();

            expect($schema['properties']['items']['description'])->toBe('List of items');
        });

        it('adds array property with raw array items', function () {
            $schema = SchemaBuilder::object()
                ->arrayProperty('ids', ['type' => 'integer'], required: true)
                ->build();

            expect($schema['properties']['ids']['items'])->toBe(['type' => 'integer'])
                ->and($schema['required'])->toContain('ids');
        });

        it('adds array property with only minItems', function () {
            $schema = SchemaBuilder::object()
                ->arrayProperty('tags', ['type' => 'string'], minItems: 1)
                ->build();

            $prop = $schema['properties']['tags'];
            expect($prop['minItems'])->toBe(1)
                ->and($prop)->not->toHaveKey('maxItems');
        });

        it('adds array property with only maxItems', function () {
            $schema = SchemaBuilder::object()
                ->arrayProperty('tags', ['type' => 'string'], maxItems: 5)
                ->build();

            $prop = $schema['properties']['tags'];
            expect($prop['maxItems'])->toBe(5)
                ->and($prop)->not->toHaveKey('minItems');
        });
    });

    describe('object property', function () {
        it('adds object property with raw array schema', function () {
            $schema = SchemaBuilder::object()
                ->objectProperty('address', [
                    'type' => 'object',
                    'properties' => ['street' => ['type' => 'string']],
                ], description: 'Mailing address', required: true)
                ->build();

            $prop = $schema['properties']['address'];
            expect($prop['type'])->toBe('object')
                ->and($prop['description'])->toBe('Mailing address')
                ->and($schema['required'])->toContain('address');
        });
    });

    describe('enum property', function () {
        it('adds enum property without description', function () {
            $schema = SchemaBuilder::object()
                ->enumProperty('size', ['S', 'M', 'L', 'XL'])
                ->build();

            $prop = $schema['properties']['size'];
            expect($prop['type'])->toBe('string')
                ->and($prop['enum'])->toBe(['S', 'M', 'L', 'XL'])
                ->and($prop)->not->toHaveKey('description');
        });
    });

    describe('property method', function () {
        it('adds custom property schema', function () {
            $schema = SchemaBuilder::object()
                ->property('custom', ['type' => 'string', 'format' => 'date-time'], required: true)
                ->build();

            expect($schema['properties']['custom']['format'])->toBe('date-time')
                ->and($schema['required'])->toContain('custom');
        });

        it('does not duplicate required entries', function () {
            $schema = SchemaBuilder::object()
                ->property('name', ['type' => 'string'], required: true)
                ->property('name', ['type' => 'string', 'minLength' => 1], required: true)
                ->build();

            $requiredCount = array_count_values($schema['required'])['name'] ?? 0;
            expect($requiredCount)->toBe(1);
        });
    });

    describe('required method', function () {
        it('accepts a single string', function () {
            $schema = SchemaBuilder::object()
                ->stringProperty('name')
                ->required('name')
                ->build();

            expect($schema['required'])->toContain('name');
        });

        it('accepts array of strings', function () {
            $schema = SchemaBuilder::object()
                ->stringProperty('a')
                ->stringProperty('b')
                ->required(['a', 'b'])
                ->build();

            expect($schema['required'])->toContain('a')
                ->and($schema['required'])->toContain('b');
        });

        it('does not duplicate required entries', function () {
            $schema = SchemaBuilder::object()
                ->stringProperty('name', required: true)
                ->required('name')
                ->build();

            $count = array_count_values($schema['required'])['name'] ?? 0;
            expect($count)->toBe(1);
        });
    });

    describe('items method', function () {
        it('sets items from SchemaBuilder', function () {
            $schema = SchemaBuilder::array()
                ->items(SchemaBuilder::object()->stringProperty('name'))
                ->build();

            expect($schema['items']['type'])->toBe('object')
                ->and($schema['items']['properties'])->toHaveKey('name');
        });

        it('sets items from raw array', function () {
            $schema = SchemaBuilder::array()
                ->items(['type' => 'integer'])
                ->build();

            expect($schema['items'])->toBe(['type' => 'integer']);
        });
    });

    describe('minItems and maxItems', function () {
        it('sets minItems on array schema', function () {
            $schema = SchemaBuilder::array()
                ->items(['type' => 'string'])
                ->minItems(1)
                ->build();

            expect($schema['minItems'])->toBe(1);
        });

        it('sets maxItems on array schema', function () {
            $schema = SchemaBuilder::array()
                ->items(['type' => 'string'])
                ->maxItems(100)
                ->build();

            expect($schema['maxItems'])->toBe(100);
        });
    });

    describe('additionalProperties', function () {
        it('allows additional properties with true', function () {
            $schema = SchemaBuilder::object()
                ->additionalProperties(true)
                ->build();

            expect($schema['additionalProperties'])->toBeTrue();
        });

        it('allows additional properties with schema', function () {
            $schema = SchemaBuilder::object()
                ->additionalProperties(['type' => 'string'])
                ->build();

            expect($schema['additionalProperties'])->toBe(['type' => 'string']);
        });

        it('defaults to true when called without arguments', function () {
            $schema = SchemaBuilder::object()
                ->additionalProperties()
                ->build();

            expect($schema['additionalProperties'])->toBeTrue();
        });
    });

    describe('default value', function () {
        it('sets default string value', function () {
            $schema = SchemaBuilder::string()
                ->default('unknown')
                ->build();

            expect($schema['default'])->toBe('unknown');
        });

        it('sets default null value', function () {
            $schema = SchemaBuilder::string()
                ->default(null)
                ->build();

            expect($schema['default'])->toBeNull();
        });
    });

    describe('from existing schema', function () {
        it('creates from schema without properties', function () {
            $schema = SchemaBuilder::from(['type' => 'string'])
                ->build();

            expect($schema['type'])->toBe('string');
        });

        it('creates from schema without required', function () {
            $schema = SchemaBuilder::from([
                'type' => 'object',
                'properties' => ['name' => ['type' => 'string']],
            ])->build();

            expect($schema['properties'])->toHaveKey('name')
                ->and($schema)->not->toHaveKey('required');
        });

        it('creates from empty schema and allows adding properties', function () {
            // When from([]) is called, the constructor sets type='object' but
            // $builder->schema = $schema overwrites it with the empty array.
            // The builder still functions for adding properties.
            $schema = SchemaBuilder::from([])
                ->stringProperty('name')
                ->build();

            expect($schema['properties'])->toHaveKey('name');
        });
    });

    describe('toArray', function () {
        it('returns same result as build', function () {
            $builder = SchemaBuilder::object()
                ->stringProperty('name', required: true)
                ->integerProperty('age');

            expect($builder->toArray())->toBe($builder->build());
        });
    });

    describe('toJson', function () {
        it('produces valid JSON with default flags', function () {
            $json = SchemaBuilder::object()
                ->stringProperty('name')
                ->toJson();

            $decoded = json_decode($json, true);
            expect($decoded)->not->toBeNull()
                ->and($decoded['type'])->toBe('object');
        });

        it('produces compact JSON with zero flags', function () {
            $json = SchemaBuilder::object()
                ->stringProperty('test')
                ->toJson(0);

            expect($json)->not->toContain("\n");
        });
    });

    describe('build output', function () {
        it('excludes properties key when no properties', function () {
            $schema = SchemaBuilder::object()->build();

            expect($schema)->not->toHaveKey('properties')
                ->and($schema)->not->toHaveKey('required');
        });

        it('deduplicates required array', function () {
            $schema = SchemaBuilder::object()
                ->stringProperty('name', required: true)
                ->required(['name', 'name'])
                ->build();

            expect($schema['required'])->toBe(['name']);
        });
    });

    describe('type constants', function () {
        it('defines correct type constants', function () {
            expect(SchemaBuilder::TYPE_STRING)->toBe('string')
                ->and(SchemaBuilder::TYPE_NUMBER)->toBe('number')
                ->and(SchemaBuilder::TYPE_INTEGER)->toBe('integer')
                ->and(SchemaBuilder::TYPE_BOOLEAN)->toBe('boolean')
                ->and(SchemaBuilder::TYPE_ARRAY)->toBe('array')
                ->and(SchemaBuilder::TYPE_OBJECT)->toBe('object')
                ->and(SchemaBuilder::TYPE_NULL)->toBe('null');
        });
    });
});
