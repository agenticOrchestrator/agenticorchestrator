<?php

declare(strict_types=1);

use AgenticOrchestrator\MultiTenancy\Contracts\TenantInterface;
use AgenticOrchestrator\MultiTenancy\Tenant;

// Mock team model
class MockTeamModel
{
    public int $id = 1;

    public string $name = 'Test Team';

    public ?object $owner = null;

    public array $users = [];

    public function hasUser(object $user): bool
    {
        return in_array($user, $this->users);
    }
}

// Mock Jetstream-like team
class MockJetstreamTeam
{
    public int $id = 2;

    public string $name = 'Jetstream Team';

    public bool $personal_team = false;

    public ?object $owner = null;

    public function owner()
    {
        return $this->owner;
    }

    public function hasUser(object $user): bool
    {
        return true;
    }
}

test('creates tenant from generic model', function () {
    $model = new MockTeamModel;

    $tenant = Tenant::fromModel($model);

    expect($tenant)->toBeInstanceOf(TenantInterface::class);
    expect($tenant->getTenantKey())->toBe(1);
    expect($tenant->getTenantName())->toBe('Test Team');
    expect($tenant->getModel())->toBe($model);
});

test('creates tenant with custom mapping', function () {
    $model = new MockTeamModel;
    $model->id = 42;
    $model->name = 'Custom Team';

    $tenant = new Tenant($model, [
        'key' => 'id',
        'name' => 'name',
    ]);

    expect($tenant->getTenantKey())->toBe(42);
    expect($tenant->getTenantName())->toBe('Custom Team');
});

test('creates tenant with callable mapping', function () {
    $model = new MockTeamModel;
    $model->id = 100;

    $tenant = new Tenant($model, [
        'key' => fn ($m) => 'custom_'.$m->id,
        'name' => fn ($m) => strtoupper($m->name),
    ]);

    expect($tenant->getTenantKey())->toBe('custom_100');
    expect($tenant->getTenantName())->toBe('TEST TEAM');
});

test('checks member access', function () {
    $model = new MockTeamModel;
    $user = new stdClass;
    $user->id = 1;

    $model->users = [$user];

    $tenant = Tenant::fromModel($model);

    expect($tenant->hasMember($user))->toBeTrue();
    expect($tenant->hasMember(new stdClass))->toBeFalse();
});

test('detects jetstream team mapping', function () {
    $owner = new stdClass;
    $owner->id = 1;

    $model = new MockJetstreamTeam;
    $model->owner = $owner;

    $tenant = Tenant::fromModel($model);

    expect($tenant->getTenantKey())->toBe(2);
    expect($tenant->getTenantName())->toBe('Jetstream Team');
    expect($tenant->getTenantOwner())->toBe($owner);
});

test('provides dynamic property access to model', function () {
    $model = new MockTeamModel;
    $model->name = 'Dynamic Access';

    $tenant = Tenant::fromModel($model);

    expect($tenant->name)->toBe('Dynamic Access');
    expect($tenant->id)->toBe(1);
});

test('returns empty config when not configured', function () {
    $model = new MockTeamModel;

    $tenant = Tenant::fromModel($model);

    expect($tenant->getTenantConfig())->toBeArray();
});
