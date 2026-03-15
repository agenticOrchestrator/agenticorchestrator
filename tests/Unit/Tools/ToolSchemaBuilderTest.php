<?php

declare(strict_types=1);

use AgenticOrchestrator\Tools\Attributes\Tool;
use AgenticOrchestrator\Tools\Attributes\ToolParameter;
use AgenticOrchestrator\Tools\ToolSchemaBuilder;

// Test helper classes with various parameter configurations

class SchemaTestBasicTool
{
    #[Tool('Get weather for a location')]
    public function getWeather(
        #[ToolParameter('The city name')]
        string $location,
        #[ToolParameter('Temperature unit', enum: ['celsius', 'fahrenheit'])]
        string $unit = 'celsius'
    ): array {
        return [];
    }

    #[Tool('Calculate sum')]
    public function calculateSum(
        int $a,
        int $b
    ): int {
        return $a + $b;
    }
}

class SchemaTestNameOverrideTool
{
    #[Tool('Search the web', name: 'web_search')]
    public function search(string $query): array
    {
        return [];
    }
}

class SchemaTestAllTypesTool
{
    #[Tool('Test all PHP types')]
    public function allTypes(
        string $str,
        int $integer,
        float $floating,
        bool $boolean,
        array $arr,
        object $obj,
    ): void {}
}

class SchemaTestConstraintsTool
{
    #[Tool('Test constraints')]
    public function withConstraints(
        #[ToolParameter('A string', format: 'email', minLength: 5, maxLength: 100, pattern: '^[a-z]+$')]
        string $email,
        #[ToolParameter('A number', minimum: 0, maximum: 100)]
        int $score,
    ): void {}
}

class SchemaTestNoTypeTool
{
    #[Tool('Test no type hint')]
    public function noType($value): void {}
}

class SchemaTestNullDefaultTool
{
    #[Tool('Test null default')]
    public function withNullDefault(
        string $required,
        ?string $optional = null
    ): void {}
}

class SchemaTestRequiredOverrideTool
{
    #[Tool('Test required override')]
    public function withRequiredOverride(
        #[ToolParameter('Forced required', required: true)]
        string $forceRequired,
        #[ToolParameter('Forced optional', required: false)]
        string $forceOptional,
    ): void {}
}

class SchemaTestNoAttributeTool
{
    #[Tool('Test params without attributes')]
    public function noAttributes(
        string $plain,
        int $count = 5
    ): void {}
}

describe('buildFromMethod', function () {
    it('builds schema from method with tool attribute', function () {
        $builder = new ToolSchemaBuilder;
        $reflection = new ReflectionMethod(SchemaTestBasicTool::class, 'getWeather');
        $toolAttr = $reflection->getAttributes(Tool::class)[0]->newInstance();

        $schema = $builder->buildFromMethod($reflection, $toolAttr);

        expect($schema['type'])->toBe('function');
        expect($schema['function']['name'])->toBe('getWeather');
        expect($schema['function']['description'])->toBe('Get weather for a location');
        expect($schema['function'])->toHaveKey('parameters');
    });

    it('uses tool attribute name override', function () {
        $builder = new ToolSchemaBuilder;
        $reflection = new ReflectionMethod(SchemaTestNameOverrideTool::class, 'search');
        $toolAttr = $reflection->getAttributes(Tool::class)[0]->newInstance();

        $schema = $builder->buildFromMethod($reflection, $toolAttr);

        expect($schema['function']['name'])->toBe('web_search');
    });

    it('builds parameter properties correctly', function () {
        $builder = new ToolSchemaBuilder;
        $reflection = new ReflectionMethod(SchemaTestBasicTool::class, 'getWeather');
        $toolAttr = $reflection->getAttributes(Tool::class)[0]->newInstance();

        $schema = $builder->buildFromMethod($reflection, $toolAttr);
        $params = $schema['function']['parameters'];

        expect($params['type'])->toBe('object');
        expect($params['properties'])->toHaveKeys(['location', 'unit']);
        expect($params['properties']['location']['type'])->toBe('string');
        expect($params['properties']['location']['description'])->toBe('The city name');
    });

    it('identifies required parameters', function () {
        $builder = new ToolSchemaBuilder;
        $reflection = new ReflectionMethod(SchemaTestBasicTool::class, 'getWeather');
        $toolAttr = $reflection->getAttributes(Tool::class)[0]->newInstance();

        $schema = $builder->buildFromMethod($reflection, $toolAttr);

        expect($schema['function']['parameters']['required'])->toContain('location');
        expect($schema['function']['parameters']['required'])->not->toContain('unit');
    });

    it('handles enum parameters', function () {
        $builder = new ToolSchemaBuilder;
        $reflection = new ReflectionMethod(SchemaTestBasicTool::class, 'getWeather');
        $toolAttr = $reflection->getAttributes(Tool::class)[0]->newInstance();

        $schema = $builder->buildFromMethod($reflection, $toolAttr);

        expect($schema['function']['parameters']['properties']['unit']['enum'])
            ->toBe(['celsius', 'fahrenheit']);
    });

    it('includes default values', function () {
        $builder = new ToolSchemaBuilder;
        $reflection = new ReflectionMethod(SchemaTestBasicTool::class, 'getWeather');
        $toolAttr = $reflection->getAttributes(Tool::class)[0]->newInstance();

        $schema = $builder->buildFromMethod($reflection, $toolAttr);

        expect($schema['function']['parameters']['properties']['unit']['default'])
            ->toBe('celsius');
    });

    it('does not include null default values', function () {
        $builder = new ToolSchemaBuilder;
        $reflection = new ReflectionMethod(SchemaTestNullDefaultTool::class, 'withNullDefault');
        $toolAttr = $reflection->getAttributes(Tool::class)[0]->newInstance();

        $schema = $builder->buildFromMethod($reflection, $toolAttr);

        expect($schema['function']['parameters']['properties']['optional'])
            ->not->toHaveKey('default');
    });
});

describe('PHP type to JSON Schema type mapping', function () {
    it('maps int to integer', function () {
        $builder = new ToolSchemaBuilder;
        $reflection = new ReflectionMethod(SchemaTestBasicTool::class, 'calculateSum');
        $toolAttr = $reflection->getAttributes(Tool::class)[0]->newInstance();

        $schema = $builder->buildFromMethod($reflection, $toolAttr);

        expect($schema['function']['parameters']['properties']['a']['type'])->toBe('integer');
        expect($schema['function']['parameters']['properties']['b']['type'])->toBe('integer');
    });

    it('maps all PHP types correctly', function () {
        $builder = new ToolSchemaBuilder;
        $reflection = new ReflectionMethod(SchemaTestAllTypesTool::class, 'allTypes');
        $toolAttr = $reflection->getAttributes(Tool::class)[0]->newInstance();

        $schema = $builder->buildFromMethod($reflection, $toolAttr);
        $props = $schema['function']['parameters']['properties'];

        expect($props['str']['type'])->toBe('string');
        expect($props['integer']['type'])->toBe('integer');
        expect($props['floating']['type'])->toBe('number');
        expect($props['boolean']['type'])->toBe('boolean');
        expect($props['arr']['type'])->toBe('array');
        expect($props['obj']['type'])->toBe('object');
    });

    it('defaults to string when no type hint', function () {
        $builder = new ToolSchemaBuilder;
        $reflection = new ReflectionMethod(SchemaTestNoTypeTool::class, 'noType');
        $toolAttr = $reflection->getAttributes(Tool::class)[0]->newInstance();

        $schema = $builder->buildFromMethod($reflection, $toolAttr);

        expect($schema['function']['parameters']['properties']['value']['type'])->toBe('string');
    });
});

describe('constraint parameters', function () {
    it('includes format constraint', function () {
        $builder = new ToolSchemaBuilder;
        $reflection = new ReflectionMethod(SchemaTestConstraintsTool::class, 'withConstraints');
        $toolAttr = $reflection->getAttributes(Tool::class)[0]->newInstance();

        $schema = $builder->buildFromMethod($reflection, $toolAttr);
        $emailProp = $schema['function']['parameters']['properties']['email'];

        expect($emailProp['format'])->toBe('email');
    });

    it('includes minLength and maxLength constraints', function () {
        $builder = new ToolSchemaBuilder;
        $reflection = new ReflectionMethod(SchemaTestConstraintsTool::class, 'withConstraints');
        $toolAttr = $reflection->getAttributes(Tool::class)[0]->newInstance();

        $schema = $builder->buildFromMethod($reflection, $toolAttr);
        $emailProp = $schema['function']['parameters']['properties']['email'];

        expect($emailProp['minLength'])->toBe(5);
        expect($emailProp['maxLength'])->toBe(100);
    });

    it('includes minimum and maximum constraints', function () {
        $builder = new ToolSchemaBuilder;
        $reflection = new ReflectionMethod(SchemaTestConstraintsTool::class, 'withConstraints');
        $toolAttr = $reflection->getAttributes(Tool::class)[0]->newInstance();

        $schema = $builder->buildFromMethod($reflection, $toolAttr);
        $scoreProp = $schema['function']['parameters']['properties']['score'];

        expect($scoreProp['minimum'])->toBe(0);
        expect($scoreProp['maximum'])->toBe(100);
    });

    it('includes pattern constraint', function () {
        $builder = new ToolSchemaBuilder;
        $reflection = new ReflectionMethod(SchemaTestConstraintsTool::class, 'withConstraints');
        $toolAttr = $reflection->getAttributes(Tool::class)[0]->newInstance();

        $schema = $builder->buildFromMethod($reflection, $toolAttr);
        $emailProp = $schema['function']['parameters']['properties']['email'];

        expect($emailProp['pattern'])->toBe('^[a-z]+$');
    });
});

describe('required override via ToolParameter attribute', function () {
    it('respects required override to true on optional param', function () {
        $builder = new ToolSchemaBuilder;
        $reflection = new ReflectionMethod(SchemaTestRequiredOverrideTool::class, 'withRequiredOverride');
        $toolAttr = $reflection->getAttributes(Tool::class)[0]->newInstance();

        $schema = $builder->buildFromMethod($reflection, $toolAttr);

        expect($schema['function']['parameters']['required'])->toContain('forceRequired');
    });

    it('respects required override to false on required param', function () {
        $builder = new ToolSchemaBuilder;
        $reflection = new ReflectionMethod(SchemaTestRequiredOverrideTool::class, 'withRequiredOverride');
        $toolAttr = $reflection->getAttributes(Tool::class)[0]->newInstance();

        $schema = $builder->buildFromMethod($reflection, $toolAttr);

        expect($schema['function']['parameters']['required'])->not->toContain('forceOptional');
    });
});

describe('parameters without ToolParameter attribute', function () {
    it('handles plain parameters without attribute', function () {
        $builder = new ToolSchemaBuilder;
        $reflection = new ReflectionMethod(SchemaTestNoAttributeTool::class, 'noAttributes');
        $toolAttr = $reflection->getAttributes(Tool::class)[0]->newInstance();

        $schema = $builder->buildFromMethod($reflection, $toolAttr);
        $props = $schema['function']['parameters']['properties'];

        expect($props['plain']['type'])->toBe('string');
        expect($props['plain'])->not->toHaveKey('description');
        expect($props['count']['type'])->toBe('integer');
        expect($props['count']['default'])->toBe(5);
    });

    it('marks plain param without default as required', function () {
        $builder = new ToolSchemaBuilder;
        $reflection = new ReflectionMethod(SchemaTestNoAttributeTool::class, 'noAttributes');
        $toolAttr = $reflection->getAttributes(Tool::class)[0]->newInstance();

        $schema = $builder->buildFromMethod($reflection, $toolAttr);

        expect($schema['function']['parameters']['required'])->toContain('plain');
        expect($schema['function']['parameters']['required'])->not->toContain('count');
    });
});

describe('buildFromMethods', function () {
    it('builds schemas for multiple methods', function () {
        $builder = new ToolSchemaBuilder;

        $method1 = new ReflectionMethod(SchemaTestBasicTool::class, 'getWeather');
        $attr1 = $method1->getAttributes(Tool::class)[0]->newInstance();

        $method2 = new ReflectionMethod(SchemaTestBasicTool::class, 'calculateSum');
        $attr2 = $method2->getAttributes(Tool::class)[0]->newInstance();

        $schemas = $builder->buildFromMethods([
            ['method' => $method1, 'attribute' => $attr1],
            ['method' => $method2, 'attribute' => $attr2],
        ]);

        expect($schemas)->toHaveCount(2);
        expect($schemas[0]['function']['name'])->toBe('getWeather');
        expect($schemas[1]['function']['name'])->toBe('calculateSum');
    });

    it('returns empty array for empty input', function () {
        $builder = new ToolSchemaBuilder;

        $schemas = $builder->buildFromMethods([]);

        expect($schemas)->toBeEmpty();
    });
});
