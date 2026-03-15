<?php

declare(strict_types=1);

use AgenticOrchestrator\StructuredOutput\StructuredResponse;

describe('StructuredResponse', function () {
    it('constructs from array', function () {
        $response = new StructuredResponse(['name' => 'John', 'age' => 30]);

        expect($response->get('name'))->toBe('John');
        expect($response->get('age'))->toBe(30);
    });

    it('constructs from JSON string', function () {
        $response = new StructuredResponse('{"name":"Jane","active":true}');

        expect($response->get('name'))->toBe('Jane');
        expect($response->get('active'))->toBeTrue();
    });

    it('throws on invalid JSON string', function () {
        expect(fn () => new StructuredResponse('not valid json'))
            ->toThrow(InvalidArgumentException::class, 'Invalid JSON');
    });

    it('creates from fromJson factory', function () {
        $response = StructuredResponse::fromJson('{"key":"value"}');

        expect($response)->toBeInstanceOf(StructuredResponse::class);
        expect($response->get('key'))->toBe('value');
    });

    it('creates from fromArray factory', function () {
        $response = StructuredResponse::fromArray(['key' => 'value']);

        expect($response)->toBeInstanceOf(StructuredResponse::class);
        expect($response->get('key'))->toBe('value');
    });

    it('gets values with dot notation', function () {
        $response = new StructuredResponse([
            'user' => [
                'profile' => [
                    'name' => 'Alice',
                ],
            ],
        ]);

        expect($response->get('user.profile.name'))->toBe('Alice');
    });

    it('returns default for missing keys', function () {
        $response = new StructuredResponse(['a' => 1]);

        expect($response->get('missing'))->toBeNull();
        expect($response->get('missing', 'fallback'))->toBe('fallback');
    });

    it('checks key existence', function () {
        $response = new StructuredResponse(['exists' => null, 'value' => 'yes']);

        expect($response->has('exists'))->toBeTrue();
        expect($response->has('value'))->toBeTrue();
        expect($response->has('missing'))->toBeFalse();
    });

    it('gets typed string values', function () {
        $response = new StructuredResponse(['name' => 'Bob', 'count' => 42]);

        expect($response->string('name'))->toBe('Bob');
        expect($response->string('count'))->toBe('');
        expect($response->string('missing', 'default'))->toBe('default');
    });

    it('gets typed integer values', function () {
        $response = new StructuredResponse(['count' => 42, 'price' => '99', 'name' => 'test']);

        expect($response->integer('count'))->toBe(42);
        expect($response->integer('price'))->toBe(99);
        expect($response->integer('name'))->toBe(0);
        expect($response->integer('missing', 5))->toBe(5);
    });

    it('gets typed float values', function () {
        $response = new StructuredResponse(['score' => 0.95, 'price' => '19.99', 'name' => 'test']);

        expect($response->float('score'))->toBe(0.95);
        expect($response->float('price'))->toBe(19.99);
        expect($response->float('name'))->toBe(0.0);
        expect($response->float('missing', 1.5))->toBe(1.5);
    });

    it('gets typed boolean values', function () {
        $response = new StructuredResponse([
            'active' => true,
            'disabled' => false,
            'yes_str' => 'yes',
            'true_str' => 'true',
            'one_str' => '1',
            'no_str' => 'no',
            'zero' => 0,
        ]);

        expect($response->boolean('active'))->toBeTrue();
        expect($response->boolean('disabled'))->toBeFalse();
        expect($response->boolean('yes_str'))->toBeTrue();
        expect($response->boolean('true_str'))->toBeTrue();
        expect($response->boolean('one_str'))->toBeTrue();
        expect($response->boolean('no_str'))->toBeFalse();
        expect($response->boolean('zero'))->toBeFalse();
        expect($response->boolean('missing'))->toBeFalse();
        expect($response->boolean('missing', true))->toBeTrue();
    });

    it('gets typed array values', function () {
        $response = new StructuredResponse([
            'tags' => ['php', 'laravel'],
            'name' => 'not-array',
        ]);

        expect($response->array('tags'))->toBe(['php', 'laravel']);
        expect($response->array('name'))->toBe([]);
        expect($response->array('missing', ['default']))->toBe(['default']);
    });

    it('gets nested object as StructuredResponse', function () {
        $response = new StructuredResponse([
            'user' => ['name' => 'Alice', 'age' => 25],
        ]);

        $user = $response->object('user');

        expect($user)->toBeInstanceOf(StructuredResponse::class);
        expect($user->get('name'))->toBe('Alice');
        expect($user->get('age'))->toBe(25);
    });

    it('returns null for non-array object access', function () {
        $response = new StructuredResponse(['name' => 'string-value']);

        expect($response->object('name'))->toBeNull();
        expect($response->object('missing'))->toBeNull();
    });

    it('gets items as StructuredResponse array', function () {
        $response = new StructuredResponse([
            'users' => [
                ['name' => 'Alice'],
                ['name' => 'Bob'],
            ],
        ]);

        $items = $response->items('users');

        expect($items)->toHaveCount(2);
        expect($items[0])->toBeInstanceOf(StructuredResponse::class);
        expect($items[0]->get('name'))->toBe('Alice');
        expect($items[1]->get('name'))->toBe('Bob');
    });

    it('maps items through callback', function () {
        $response = new StructuredResponse([
            'users' => [
                ['name' => 'Alice'],
                ['name' => 'Bob'],
            ],
        ]);

        $names = $response->map('users', fn (StructuredResponse $item) => $item->get('name'));

        expect($names)->toBe(['Alice', 'Bob']);
    });

    it('plucks values from array items', function () {
        $response = new StructuredResponse([
            'users' => [
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
            ],
        ]);

        $names = $response->pluck('users', 'name');

        expect($names)->toBe(['Alice', 'Bob']);
    });

    it('plucks values with key mapping', function () {
        $response = new StructuredResponse([
            'users' => [
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
            ],
        ]);

        $names = $response->pluck('users', 'name', 'id');

        expect($names)->toBe([1 => 'Alice', 2 => 'Bob']);
    });

    it('validates against schema with required fields', function () {
        $schema = [
            'type' => 'object',
            'required' => ['name', 'email'],
            'properties' => [
                'name' => ['type' => 'string'],
                'email' => ['type' => 'string'],
            ],
        ];

        $valid = new StructuredResponse(['name' => 'Alice', 'email' => 'alice@test.com'], $schema);
        expect($valid->isValid())->toBeTrue();
        expect($valid->getErrors())->toBeEmpty();

        $invalid = new StructuredResponse(['name' => 'Alice'], $schema);
        expect($invalid->isValid())->toBeFalse();
        expect($invalid->getErrors())->not->toBeEmpty();
    });

    it('validates type constraints', function () {
        $schema = [
            'type' => 'object',
            'properties' => [
                'age' => ['type' => 'integer'],
            ],
        ];

        $valid = new StructuredResponse(['age' => 25], $schema);
        expect($valid->isValid())->toBeTrue();

        $invalid = new StructuredResponse(['age' => 'not-a-number'], $schema);
        expect($invalid->isValid())->toBeFalse();
    });

    it('validates enum constraints', function () {
        $schema = [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'enum' => ['active', 'inactive']],
            ],
        ];

        $valid = new StructuredResponse(['status' => 'active'], $schema);
        expect($valid->isValid())->toBeTrue();

        $invalid = new StructuredResponse(['status' => 'pending'], $schema);
        expect($invalid->isValid())->toBeFalse();
    });

    it('validates nested properties', function () {
        $schema = [
            'type' => 'object',
            'properties' => [
                'user' => [
                    'type' => 'object',
                    'properties' => [
                        'age' => ['type' => 'integer'],
                    ],
                ],
            ],
        ];

        $valid = new StructuredResponse(['user' => ['age' => 25]], $schema);
        expect($valid->isValid())->toBeTrue();

        $invalid = new StructuredResponse(['user' => ['age' => 'old']], $schema);
        expect($invalid->isValid())->toBeFalse();
    });

    it('returns true for validate without schema', function () {
        $response = new StructuredResponse(['any' => 'data']);

        expect($response->validate())->toBeTrue();
    });

    it('converts to array', function () {
        $data = ['name' => 'Alice', 'age' => 30];
        $response = new StructuredResponse($data);

        expect($response->toArray())->toBe($data);
        expect($response->all())->toBe($data);
    });

    it('converts to JSON', function () {
        $response = new StructuredResponse(['name' => 'Alice']);

        expect($response->toJson())->toBe('{"name":"Alice"}');
    });

    it('supports json serialization', function () {
        $response = new StructuredResponse(['key' => 'value']);
        $json = json_encode($response);

        expect($json)->toBe('{"key":"value"}');
    });

    it('filters with only', function () {
        $response = new StructuredResponse(['a' => 1, 'b' => 2, 'c' => 3]);

        expect($response->only(['a', 'c']))->toBe(['a' => 1, 'c' => 3]);
    });

    it('filters with except', function () {
        $response = new StructuredResponse(['a' => 1, 'b' => 2, 'c' => 3]);

        expect($response->except(['b']))->toBe(['a' => 1, 'c' => 3]);
    });

    it('supports array access', function () {
        $response = new StructuredResponse(['name' => 'Alice', 'age' => 30]);

        expect(isset($response['name']))->toBeTrue();
        expect(isset($response['missing']))->toBeFalse();
        expect($response['name'])->toBe('Alice');
        expect($response['age'])->toBe(30);
    });

    it('supports array access set and unset', function () {
        $response = new StructuredResponse(['name' => 'Alice']);

        $response['email'] = 'alice@test.com';
        expect($response['email'])->toBe('alice@test.com');

        unset($response['email']);
        expect(isset($response['email']))->toBeFalse();
    });

    it('supports magic property access', function () {
        $response = new StructuredResponse(['name' => 'Alice', 'age' => 30]);

        expect($response->name)->toBe('Alice');
        expect($response->age)->toBe(30);
        expect(isset($response->name))->toBeTrue();
        expect(isset($response->missing))->toBeFalse();
    });
});
