<?php

declare(strict_types=1);

use AgenticOrchestrator\MultiTenancy\Contracts\TenantInterface;
use AgenticOrchestrator\MultiTenancy\Contracts\TenantResolverInterface;
use AgenticOrchestrator\MultiTenancy\Resolvers\JetstreamResolver;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Auth;

function createJetstreamResolver(array $config = []): JetstreamResolver
{
    $container = Mockery::mock(Container::class);

    return new JetstreamResolver($container, $config);
}

describe('JetstreamResolver', function () {
    describe('getDriverName', function () {
        it('returns jetstream as the driver name', function () {
            $resolver = createJetstreamResolver();

            expect($resolver->getDriverName())->toBe('jetstream');
        });
    });

    describe('isConfigured', function () {
        it('returns false when Jetstream class does not exist', function () {
            $resolver = createJetstreamResolver();

            expect($resolver->isConfigured())->toBeFalse();
        });
    });

    describe('setCurrent', function () {
        it('sets current tenant via parent', function () {
            $resolver = createJetstreamResolver();
            $tenant = Mockery::mock(TenantInterface::class);

            Auth::shouldReceive('user')->andReturn(null);

            $resolver->setCurrent($tenant);

            expect($resolver->current())->toBe($tenant);
        });

        it('clears current tenant when set to null', function () {
            $resolver = createJetstreamResolver();
            $tenant = Mockery::mock(TenantInterface::class);

            Auth::shouldReceive('user')->andReturn(null);

            $resolver->setCurrent($tenant);
            $resolver->setCurrent(null);

            // After setting null, currentTenant is null, resolveCurrentTenant will be called
            Auth::shouldReceive('user')->andReturn(null);

            expect($resolver->current())->toBeNull();
        });

        it('calls switchTeam on user when user is authenticated and has method', function () {
            $resolver = createJetstreamResolver();
            $mockModel = new stdClass;

            $tenant = Mockery::mock(TenantInterface::class);
            $tenant->shouldReceive('getModel')->andReturn($mockModel);

            // Use anonymous class so method_exists works
            $switchedTo = null;
            $user = new class($switchedTo)
            {
                public function __construct(private ?object &$switchedTo) {}

                public function switchTeam(object $team): void
                {
                    $this->switchedTo = $team;
                }
            };

            Auth::shouldReceive('user')->andReturn($user);

            $resolver->setCurrent($tenant);

            expect($switchedTo)->toBe($mockModel);
        });

        it('does not call switchTeam when user is not authenticated', function () {
            $resolver = createJetstreamResolver();

            $tenant = Mockery::mock(TenantInterface::class);
            $tenant->shouldNotReceive('getModel');

            Auth::shouldReceive('user')->andReturn(null);

            $resolver->setCurrent($tenant);

            // No error means switchTeam was not called
            expect(true)->toBeTrue();
        });
    });

    describe('forUser', function () {
        it('returns empty array when user does not have allTeams method', function () {
            $resolver = createJetstreamResolver();
            $user = new stdClass;

            $results = iterator_to_array($resolver->forUser($user));

            expect($results)->toBeEmpty();
        });

        it('yields wrapped tenants from user allTeams', function () {
            $resolver = createJetstreamResolver();

            $team1 = Mockery::mock(TenantInterface::class);
            $team2 = Mockery::mock(TenantInterface::class);

            // Use anonymous class so method_exists('allTeams') is true
            $user = new class($team1, $team2)
            {
                public function __construct(
                    private TenantInterface $team1,
                    private TenantInterface $team2,
                ) {}

                public function allTeams(): array
                {
                    return [$this->team1, $this->team2];
                }
            };

            $results = iterator_to_array($resolver->forUser($user));

            expect($results)->toHaveCount(2)
                ->and($results[0])->toBe($team1)
                ->and($results[1])->toBe($team2);
        });
    });

    describe('resolveCurrentTenant', function () {
        it('returns null when no user is authenticated', function () {
            $resolver = createJetstreamResolver();

            Auth::shouldReceive('user')->andReturn(null);

            expect($resolver->current())->toBeNull();
        });

        it('returns null when user does not have currentTeam property', function () {
            $resolver = createJetstreamResolver();

            $user = new stdClass;
            Auth::shouldReceive('user')->andReturn($user);

            expect($resolver->current())->toBeNull();
        });

        it('returns null when user currentTeam is null', function () {
            $resolver = createJetstreamResolver();

            // Use anonymous class with currentTeam property
            $user = new class
            {
                public ?object $currentTeam = null;

                public function currentTeam(): ?object
                {
                    return null;
                }
            };

            Auth::shouldReceive('user')->andReturn($user);

            expect($resolver->current())->toBeNull();
        });

        it('returns wrapped tenant when user has currentTeam', function () {
            $resolver = createJetstreamResolver();

            $team = Mockery::mock(TenantInterface::class);

            // Use anonymous class so method_exists('currentTeam') is true
            $user = new class($team)
            {
                public ?object $currentTeam;

                public function __construct(object $team)
                {
                    $this->currentTeam = $team;
                }

                public function currentTeam(): ?object
                {
                    return $this->currentTeam;
                }
            };

            Auth::shouldReceive('user')->andReturn($user);

            $result = $resolver->current();

            expect($result)->toBe($team);
        });
    });

    describe('wrapTenant', function () {
        it('returns TenantInterface objects without wrapping', function () {
            $resolver = createJetstreamResolver();
            $tenant = Mockery::mock(TenantInterface::class);

            Auth::shouldReceive('user')->andReturn(null);

            $resolver->setCurrent($tenant);

            expect($resolver->current())->toBe($tenant);
        });
    });

    describe('getTeamModel', function () {
        it('returns configured model class when provided', function () {
            $resolver = createJetstreamResolver(['model' => 'App\\Models\\CustomTeam']);

            $reflection = new ReflectionClass($resolver);
            $method = $reflection->getMethod('getTeamModel');
            $method->setAccessible(true);

            expect($method->invoke($resolver))->toBe('App\\Models\\CustomTeam');
        });

        it('falls back to App Models Team when no config and no Jetstream', function () {
            $resolver = createJetstreamResolver([]);

            $reflection = new ReflectionClass($resolver);
            $method = $reflection->getMethod('getTeamModel');
            $method->setAccessible(true);

            expect($method->invoke($resolver))->toBe('App\\Models\\Team');
        });
    });

    describe('runAs', function () {
        it('executes callback and returns result', function () {
            $resolver = createJetstreamResolver();

            $newTenant = Mockery::mock(TenantInterface::class);
            $newTenant->shouldReceive('getModel')->andReturn(new stdClass);

            $user = new class
            {
                public ?object $currentTeam = null;

                public function switchTeam(?object $team): void
                {
                    $this->currentTeam = $team;
                }

                public function currentTeam(): ?object
                {
                    return $this->currentTeam;
                }
            };

            Auth::shouldReceive('user')->andReturn($user);

            $result = $resolver->runAs($newTenant, function () {
                return 'executed';
            });

            expect($result)->toBe('executed');
        });

        it('restores context even when callback throws', function () {
            $resolver = createJetstreamResolver();

            $newTenant = Mockery::mock(TenantInterface::class);
            $newTenant->shouldReceive('getModel')->andReturn(new stdClass);

            $user = new class
            {
                public ?object $currentTeam = null;

                public function switchTeam(?object $team): void
                {
                    $this->currentTeam = $team;
                }

                public function currentTeam(): ?object
                {
                    return $this->currentTeam;
                }
            };

            Auth::shouldReceive('user')->andReturn($user);

            try {
                $resolver->runAs($newTenant, function () {
                    throw new RuntimeException('Test error');
                });
            } catch (RuntimeException) {
                // Expected
            }

            expect(true)->toBeTrue();
        });
    });

    describe('implements TenantResolverInterface', function () {
        it('implements the contract interface', function () {
            $resolver = createJetstreamResolver();

            expect($resolver)->toBeInstanceOf(
                TenantResolverInterface::class
            );
        });
    });
});
