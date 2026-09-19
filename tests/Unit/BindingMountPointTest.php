<?php

declare(strict_types=1);

namespace FsControl\Test\Unit;

use FsControl\Configuration\Binding;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * @covers \FsControl\Configuration\Binding
 */
class BindingMountPointTest extends TestCase
{
    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldTreatTheMountPointItselfAsBounded(): void
    {
        $binding = new Binding('$/Domain', 'Domain', 'Domain');

        self::assertTrue($binding->isBoundedFor('Domain'));
    }

    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldTreatAnIntermediateDirectoryOfADeepBindingAsBounded(): void
    {
        $binding = new Binding('$/Domain/CQRS', 'Domain/CQRS', 'CQRS');

        // "Domain" is on the way to the mount point, so it is a mount point on its own
        self::assertTrue($binding->isBoundedFor('Domain'));
        self::assertTrue($binding->isBoundedFor('Domain/CQRS'));
    }

    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldNotTreatAPathSharingCharactersWithTheBindingAsBounded(): void
    {
        $binding = new Binding('$/Domain', 'Domain', 'Domain');

        // "Domain" literally contains "main" and "omai": a substring is not a mount point
        self::assertFalse($binding->isBoundedFor('main'));
        self::assertFalse($binding->isBoundedFor('omai'));
        self::assertFalse($binding->isBoundedFor('Dom'));
    }

    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldNotTreatATopLevelNamesakeOfADeepBindingSegmentAsBounded(): void
    {
        $binding = new Binding('$/Domain/CQRS', 'Domain/CQRS', 'CQRS');

        // a top-level "CQRS" is a different directory than the bound "Domain/CQRS"
        self::assertFalse($binding->isBoundedFor('CQRS'));
        self::assertFalse($binding->isBoundedFor('QRS'));
    }

    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldTreatTheLiteralPrefixOfAWildcardBindingAsBounded(): void
    {
        $binding = new Binding('$/Domain/**', 'Domain/**', 'Domain');

        self::assertTrue($binding->isBoundedFor('Domain'));
        self::assertFalse($binding->isBoundedFor('Dom'));
        self::assertFalse($binding->isBoundedFor('main'));
    }
}
