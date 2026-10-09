<?php
declare(strict_types=1);

namespace Tests\Unit\Container\Bindings;

use Engine\Container\Attributes\Named;
use Engine\Container\Bindings\Binding;
use Engine\Container\Bindings\BindingCatalogue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Container\Fixtures\AbstractInterface;
use Tests\Unit\Container\Fixtures\AnotherTestQualifier;
use Tests\Unit\Container\Fixtures\ClassWithMethods;
use Tests\Unit\Container\Fixtures\ConcreteClass;
use Tests\Unit\Container\Fixtures\TestQualifier;

#[Group('unit'), Group('container'), Group('bindings')]
class BindingCatalogueTest extends TestCase
{
    /**
     * - Looking up a directly registered class returns its binding.
     */
    #[Test]
    public function getReturnsBindingForRegisteredClass(): void
    {
        $binding   = new Binding(ClassWithMethods::class);
        $catalogue = new BindingCatalogue([ClassWithMethods::class => $binding], [], []);

        $this->assertSame($binding, $catalogue->get(ClassWithMethods::class));
    }

    /**
     * - Looking up a class that has no binding returns null rather than throwing,
     *   so callers can distinguish "unbound" from "bound" without exceptions.
     */
    #[Test]
    public function getReturnsNullWhenClassIsNotRegistered(): void
    {
        $catalogue = new BindingCatalogue([], [], []);

        $this->assertNull($catalogue->get(ClassWithMethods::class));
    }

    /**
     * - Looking up a class registered as an alias resolves transparently to the
     *   canonical binding it points to.
     */
    #[Test]
    public function getResolvesAliasToCanonicalBinding(): void
    {
        $binding   = new Binding(AbstractInterface::class);
        $catalogue = new BindingCatalogue(
            [AbstractInterface::class => $binding],
            [ConcreteClass::class => AbstractInterface::class],
            [],
        );

        $this->assertSame($binding, $catalogue->get(ConcreteClass::class));
    }

    /**
     * - An alias that points to a class with no binding in the catalogue returns null
     *   rather than throwing, since the abstract itself is unbound.
     */
    #[Test]
    public function getReturnsNullForUnregisteredAlias(): void
    {
        $catalogue = new BindingCatalogue([], [ConcreteClass::class => AbstractInterface::class], []);

        $this->assertNull($catalogue->get(ConcreteClass::class));
    }

    /**
     * - Normalising an alias returns the class it points to.
     */
    #[Test]
    public function resolveAliasReturnsTargetForRegisteredAlias(): void
    {
        $catalogue = new BindingCatalogue([], [ConcreteClass::class => AbstractInterface::class], []);

        $this->assertSame(AbstractInterface::class, $catalogue->resolveAlias(ConcreteClass::class));
    }

    /**
     * - Normalising a class that is not an alias returns the class unchanged.
     */
    #[Test]
    public function resolveAliasReturnsClassWhenNotAnAlias(): void
    {
        $catalogue = new BindingCatalogue([], [ConcreteClass::class => AbstractInterface::class], []);

        $this->assertSame(ClassWithMethods::class, $catalogue->resolveAlias(ClassWithMethods::class));
    }

    /**
     * - Normalisation replaces an alias once, so an alias of an alias returns the first
     *   target rather than following the chain.
     */
    #[Test]
    public function resolveAliasReplacesOnlyOnce(): void
    {
        $catalogue = new BindingCatalogue(
            [],
            [
                AbstractInterface::class => ConcreteClass::class,
                ConcreteClass::class     => ClassWithMethods::class,
            ],
            [],
        );

        $this->assertSame(ConcreteClass::class, $catalogue->resolveAlias(AbstractInterface::class));
    }

    /**
     * - Providing a Named qualifier returns the specific named child binding from
     *   within the parent, enabling multiple distinct bindings for the same type.
     */
    #[Test]
    public function getReturnsNamedChildBinding(): void
    {
        $namedBinding = new Binding(ClassWithMethods::class);
        $binding      = new Binding(ClassWithMethods::class, namedMap: ['my-name' => $namedBinding]);
        $catalogue    = new BindingCatalogue([ClassWithMethods::class => $binding], [], []);

        $this->assertSame($namedBinding, $catalogue->get(ClassWithMethods::class, new Named('my-name')));
    }

    /**
     * - Providing a name that does not exist within an existing parent binding
     *   returns null, distinguishing a missing child from a missing parent.
     */
    #[Test]
    public function getReturnsNullWhenNamedBindingNotFound(): void
    {
        $binding   = new Binding(ClassWithMethods::class);
        $catalogue = new BindingCatalogue([ClassWithMethods::class => $binding], [], []);

        $this->assertNull($catalogue->get(ClassWithMethods::class, new Named('missing')));
    }

    /**
     * - Providing a name for a class that has no binding at all returns null rather
     *   than throwing, because there is no parent to look inside.
     */
    #[Test]
    public function getReturnsNullForNamedWhenParentBindingDoesNotExist(): void
    {
        $catalogue = new BindingCatalogue([], [], []);

        $this->assertNull($catalogue->get(ClassWithMethods::class, new Named('my-name')));
    }

    /**
     * - Providing a matching qualifier instance returns the specific qualified child
     *   binding, enabling multiple qualifier-scoped bindings for the same type.
     */
    #[Test]
    public function getReturnsQualifiedChildBinding(): void
    {
        $qualifiedBinding = new Binding(ClassWithMethods::class);
        $binding          = new Binding(ClassWithMethods::class, qualifiedMap: [TestQualifier::class => $qualifiedBinding]);
        $catalogue        = new BindingCatalogue([ClassWithMethods::class => $binding], [], []);

        $this->assertSame($qualifiedBinding, $catalogue->get(ClassWithMethods::class, null, new TestQualifier()));
    }

    /**
     * - Providing a qualifier whose class does not match any registered entry in the
     *   qualified map returns null, distinguishing a non-matching qualifier from a
     *   missing parent binding.
     */
    #[Test]
    public function getReturnsNullWhenQualifierNotMatched(): void
    {
        $qualifiedBinding = new Binding(ClassWithMethods::class);
        $binding          = new Binding(ClassWithMethods::class, qualifiedMap: [TestQualifier::class => $qualifiedBinding]);
        $catalogue        = new BindingCatalogue([ClassWithMethods::class => $binding], [], []);

        $this->assertNull($catalogue->get(ClassWithMethods::class, null, new AnotherTestQualifier()));
    }

    /**
     * - Providing a qualifier for a class that has no binding at all returns null
     *   rather than throwing, because there is no parent to look inside.
     */
    #[Test]
    public function getReturnsNullForQualifierWhenParentBindingDoesNotExist(): void
    {
        $catalogue = new BindingCatalogue([], [], []);

        $this->assertNull($catalogue->get(ClassWithMethods::class, null, new TestQualifier()));
    }
}
