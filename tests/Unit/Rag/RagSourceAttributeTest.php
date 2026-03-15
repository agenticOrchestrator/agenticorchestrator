<?php

declare(strict_types=1);

use AgenticOrchestrator\Rag\Attributes\RagSource;

describe('RagSource Attribute', function () {
    it('constructs with namespace and default values', function () {
        $attribute = new RagSource('my-namespace');

        expect($attribute->namespace)->toBe('my-namespace')
            ->and($attribute->limit)->toBe(5)
            ->and($attribute->threshold)->toBe(0.7)
            ->and($attribute->contextTemplate)->toBeNull()
            ->and($attribute->enabled)->toBeTrue()
            ->and($attribute->filter)->toBe([]);
    });

    it('constructs with all parameters', function () {
        $attribute = new RagSource(
            namespace: 'custom-namespace',
            limit: 10,
            threshold: 0.85,
            contextTemplate: 'Custom template: {context}',
            enabled: false,
            filter: ['category' => 'docs']
        );

        expect($attribute->namespace)->toBe('custom-namespace')
            ->and($attribute->limit)->toBe(10)
            ->and($attribute->threshold)->toBe(0.85)
            ->and($attribute->contextTemplate)->toBe('Custom template: {context}')
            ->and($attribute->enabled)->toBeFalse()
            ->and($attribute->filter)->toBe(['category' => 'docs']);
    });

    it('getContextTemplate returns default template when contextTemplate is null', function () {
        $attribute = new RagSource('namespace');

        $template = $attribute->getContextTemplate();

        expect($template)->toContain('{context}')
            ->and($template)->toContain('Relevant Context');
    });

    it('getContextTemplate returns custom template when set', function () {
        $customTemplate = 'Here is the data: {context}';
        $attribute = new RagSource('namespace', contextTemplate: $customTemplate);

        $template = $attribute->getContextTemplate();

        expect($template)->toBe($customTemplate);
    });

    it('enabled defaults to true', function () {
        $attribute = new RagSource('namespace');

        expect($attribute->enabled)->toBeTrue();
    });

    it('filter defaults to empty array', function () {
        $attribute = new RagSource('namespace');

        expect($attribute->filter)->toBe([])
            ->and($attribute->filter)->toBeArray();
    });

    it('attribute targets class and property and is repeatable', function () {
        $reflection = new ReflectionClass(RagSource::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        expect($attributes)->toHaveCount(1);

        $attributeInstance = $attributes[0]->newInstance();

        expect($attributeInstance->flags)->toBe(
            Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE
        );
    });

    it('can be applied to a class', function () {
        #[RagSource('test-namespace')]
        class TestClass {}

        $reflection = new ReflectionClass(TestClass::class);
        $attributes = $reflection->getAttributes(RagSource::class);

        expect($attributes)->toHaveCount(1)
            ->and($attributes[0]->newInstance()->namespace)->toBe('test-namespace');
    });

    it('can be applied to a property', function () {
        class TestPropertyClass
        {
            #[RagSource('property-namespace', limit: 3)]
            public string $data;
        }

        $reflection = new ReflectionProperty(TestPropertyClass::class, 'data');
        $attributes = $reflection->getAttributes(RagSource::class);

        expect($attributes)->toHaveCount(1)
            ->and($attributes[0]->newInstance()->namespace)->toBe('property-namespace')
            ->and($attributes[0]->newInstance()->limit)->toBe(3);
    });

    it('can be applied multiple times (repeatable)', function () {
        #[RagSource('namespace-1')]
        #[RagSource('namespace-2')]
        class TestRepeatableClass {}

        $reflection = new ReflectionClass(TestRepeatableClass::class);
        $attributes = $reflection->getAttributes(RagSource::class);

        expect($attributes)->toHaveCount(2)
            ->and($attributes[0]->newInstance()->namespace)->toBe('namespace-1')
            ->and($attributes[1]->newInstance()->namespace)->toBe('namespace-2');
    });
});
