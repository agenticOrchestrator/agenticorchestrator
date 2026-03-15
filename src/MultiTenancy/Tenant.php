<?php

declare(strict_types=1);

namespace AgenticOrchestrator\MultiTenancy;

use AgenticOrchestrator\MultiTenancy\Contracts\TenantInterface;

/**
 * Generic Tenant Wrapper - Adapts any model to TenantInterface.
 *
 * This class provides a flexible way to wrap different tenant models
 * (Jetstream Team, Spatie Tenant, Stancl Tenant, etc.) into a common interface.
 */
class Tenant implements TenantInterface
{
    /**
     * Configuration for mapping model properties.
     *
     * @var array<string, string|callable>
     */
    protected array $mapping;

    /**
     * Create a new tenant wrapper.
     *
     * @param  object  $model  The underlying tenant model
     * @param  array<string, string|callable>  $mapping  Property mapping configuration
     */
    public function __construct(
        protected object $model,
        array $mapping = [],
    ) {
        $this->mapping = array_merge($this->defaultMapping(), $mapping);
    }

    /**
     * Get the tenant's unique identifier.
     */
    public function getTenantKey(): int|string
    {
        return $this->resolveProperty('key');
    }

    /**
     * Get the tenant's display name.
     */
    public function getTenantName(): string
    {
        return $this->resolveProperty('name');
    }

    /**
     * Get the tenant's owner (if applicable).
     */
    public function getTenantOwner(): ?object
    {
        return $this->resolveProperty('owner');
    }

    /**
     * Check if a user belongs to this tenant.
     */
    public function hasMember(object $user): bool
    {
        $checker = $this->mapping['has_member'] ?? null;

        if ($checker instanceof \Closure) {
            return $checker($this->model, $user);
        }

        // Default: check if model has a users/members relationship
        if (method_exists($this->model, 'users')) {
            return $this->model->users->contains($user);
        }

        if (method_exists($this->model, 'members')) {
            return $this->model->members->contains($user);
        }

        // Check if model has hasUser/hasMember method
        if (method_exists($this->model, 'hasUser')) {
            return $this->model->hasUser($user);
        }

        if (method_exists($this->model, 'hasMember')) {
            return $this->model->hasMember($user);
        }

        return false;
    }

    /**
     * Get the tenant's configuration/settings.
     *
     * @return array<string, mixed>
     */
    public function getTenantConfig(): array
    {
        $config = $this->resolveProperty('config');

        return is_array($config) ? $config : [];
    }

    /**
     * Get the underlying model instance.
     */
    public function getModel(): object
    {
        return $this->model;
    }

    /**
     * Create a tenant from a model with automatic detection.
     */
    public static function fromModel(object $model): static
    {
        $mapping = static::detectMapping($model);

        return new static($model, $mapping);
    }

    /**
     * Get default property mapping.
     *
     * @return array<string, string|callable>
     */
    protected function defaultMapping(): array
    {
        return [
            'key' => 'id',
            'name' => 'name',
            'owner' => 'owner',
            'config' => 'settings',
        ];
    }

    /**
     * Resolve a property from the model.
     */
    protected function resolveProperty(string $property): mixed
    {
        $accessor = $this->mapping[$property] ?? $property;

        if ($accessor instanceof \Closure) {
            return $accessor($this->model);
        }

        // Try method first
        if (method_exists($this->model, $accessor)) {
            return $this->model->{$accessor}();
        }

        // Try getter method
        $getter = 'get'.ucfirst($accessor);
        if (method_exists($this->model, $getter)) {
            return $this->model->{$getter}();
        }

        // Try property access
        if (property_exists($this->model, $accessor) || isset($this->model->{$accessor})) {
            return $this->model->{$accessor};
        }

        // Try Laravel model attribute
        if (method_exists($this->model, 'getAttribute')) {
            return $this->model->getAttribute($accessor);
        }

        return null;
    }

    /**
     * Detect property mapping based on model class.
     *
     * @return array<string, string|callable>
     */
    protected static function detectMapping(object $model): array
    {
        $class = get_class($model);

        // Jetstream Team
        if (str_contains($class, 'Team') && method_exists($model, 'owner')) {
            return [
                'key' => 'id',
                'name' => 'name',
                'owner' => fn ($m) => $m->owner,
                'has_member' => fn ($m, $u) => $m->hasUser($u),
            ];
        }

        // Spatie Multitenancy Tenant
        if (method_exists($model, 'makeCurrent')) {
            return [
                'key' => fn ($m) => $m->id ?? $m->getKey(),
                'name' => 'name',
            ];
        }

        // Stancl Tenancy
        if (method_exists($model, 'run')) {
            return [
                'key' => fn ($m) => $m->getTenantKey(),
                'name' => fn ($m) => $m->getAttribute('name') ?? $m->getTenantKey(),
            ];
        }

        // Filament - typically uses standard models
        if (property_exists($model, 'filamentName')) {
            return [
                'key' => 'id',
                'name' => 'filamentName',
            ];
        }

        return [];
    }

    /**
     * Dynamic property access to underlying model.
     */
    public function __get(string $name): mixed
    {
        return $this->model->{$name};
    }

    /**
     * Dynamic method calls to underlying model.
     *
     * @param  array<mixed>  $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->model->{$name}(...$arguments);
    }
}
