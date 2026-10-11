<?php
declare(strict_types=1);

namespace Tests\Unit\Database;

use Engine\Container\Bindings\BindingCatalogue;
use Engine\Container\Container;
use Engine\Container\Contracts\Resolvable;
use Engine\Container\Dependency;
use Engine\Container\Resolvers\GenericResolver;
use Engine\Container\Resolvers\ResolverCatalogue;
use Engine\Database\Attributes\Database;
use Engine\Database\Config\ConnectionConfig;
use Engine\Database\Config\DatabaseConfig;
use Engine\Database\Connection;
use Engine\Database\ConnectionFactory;
use Engine\Database\DatabaseResolver;
use Engine\Database\Exceptions\DatabaseException;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionNamedType;
use Tests\Unit\Database\Fixtures\ClassWithDatabaseDependency;
use Tests\Unit\Database\Fixtures\ClassWithInvalidDatabaseType;

#[Group('unit'), Group('database'), Group('database-resolver')]
class DatabaseResolverTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Successful resolution
    // -------------------------------------------------------------------------

    /**
     * - Resolves a Connection for a parameter with the #[Database] attribute
     *   and no explicit name, returning the primary connection.
     */
    #[Test]
    public function resolvesDefaultConnectionWhenNoNameSpecified(): void
    {
        $primary    = new Connection('primary', new PDO('sqlite::memory:'));
        $resolver   = new DatabaseResolver($this->factoryWithConnections(['primary' => $primary]));
        $dependency = $this->dependencyFrom(ClassWithDatabaseDependency::class, 'default');

        $result = $resolver->resolve($dependency, $this->buildContainer());

        $this->assertSame($primary, $result);
    }

    /**
     * - Resolves a Connection for a parameter with #[Database('secondary')],
     *   returning the named connection.
     */
    #[Test]
    public function resolvesNamedConnectionWhenNameSpecified(): void
    {
        $primary   = new Connection('primary', new PDO('sqlite::memory:'));
        $secondary = new Connection('secondary', new PDO('sqlite::memory:'));
        $resolver  = new DatabaseResolver($this->factoryWithConnections([
            'primary'   => $primary,
            'secondary' => $secondary,
        ]));
        $dependency = $this->dependencyFrom(ClassWithDatabaseDependency::class, 'secondary');

        $result = $resolver->resolve($dependency, $this->buildContainer());

        $this->assertSame($secondary, $result);
    }

    // -------------------------------------------------------------------------
    // Error: wrong resolvable attribute
    // -------------------------------------------------------------------------

    /**
     * - Throws RuntimeException when the dependency's resolvable attribute
     *   is not a Database instance.
     */
    #[Test]
    public function throwsWhenResolvableIsNotDatabaseAttribute(): void
    {
        $resolver   = new DatabaseResolver($this->factoryWithConnections([]));
        $dependency = new Dependency(
            'param',
            $this->connectionReflectionType(),
            resolvable: new class implements Resolvable {},
        );

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage(Database::class);

        $resolver->resolve($dependency, $this->buildContainer());
    }

    // -------------------------------------------------------------------------
    // Error: wrong parameter type
    // -------------------------------------------------------------------------

    /**
     * - Throws RuntimeException when the parameter type is not Connection.
     */
    #[Test]
    public function throwsWhenParameterTypeIsNotConnection(): void
    {
        $resolver   = new DatabaseResolver($this->factoryWithConnections([]));
        $dependency = $this->dependencyFrom(ClassWithInvalidDatabaseType::class, 'connection');

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage(Connection::class);

        $resolver->resolve($dependency, $this->buildContainer());
    }

    /**
     * - Throws RuntimeException when the parameter has no type.
     */
    #[Test]
    public function throwsWhenParameterHasNoType(): void
    {
        $resolver   = new DatabaseResolver($this->factoryWithConnections([]));
        $dependency = new Dependency(
            'param',
            null,
            resolvable: new Database(),
        );

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage(Connection::class);

        $resolver->resolve($dependency, $this->buildContainer());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a ConnectionFactory pre-loaded with the given connections,
     * bypassing the need for a real MySQL connection.
     *
     * @param array<string, Connection> $connections
     */
    private function factoryWithConnections(array $connections): ConnectionFactory
    {
        $primary = array_key_first($connections) ?? 'primary';
        $configs = [];

        foreach (array_keys($connections) as $name) {
            $configs[$name] = ConnectionConfig::make('127.0.0.1', 3306, null, 'test', 'test', 'test');
        }

        if (empty($configs)) {
            $configs['primary'] = ConnectionConfig::make('127.0.0.1', 3306, null, 'test', 'test', 'test');
        }

        $factory = new ConnectionFactory(DatabaseConfig::make($primary, $configs));

        // Inject pre-built connections into the factory's private cache.
        $ref = new ReflectionClass($factory);
        $ref->getProperty('connections')->setValue($factory, $connections);

        return $factory;
    }

    private function buildContainer(): Container
    {
        return new Container(
            new ResolverCatalogue([], GenericResolver::class),
            new BindingCatalogue([], [], []),
        );
    }

    private function connectionReflectionType(): ReflectionNamedType
    {
        return new ReflectionClass(ClassWithDatabaseDependency::class)
            ->getConstructor()
            ->getParameters()[0]
            ->getType()
        ;
    }

    private function dependencyFrom(string $class, string $paramName): Dependency
    {
        $constructor = new ReflectionClass($class)->getConstructor();

        foreach ($constructor->getParameters() as $param) {
            if ($param->getName() === $paramName) {
                $resolvable = null;

                foreach ($param->getAttributes(Resolvable::class, ReflectionAttribute::IS_INSTANCEOF) as $attr) {
                    $resolvable = $attr->newInstance();
                }

                return new Dependency(
                    $param->getName(),
                    $param->getType(),
                    resolvable: $resolvable,
                );
            }
        }

        throw new RuntimeException("Parameter '{$paramName}' not found on {$class}");
    }
}
