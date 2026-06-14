<?php

declare(strict_types=1);

namespace FsControl\Test\Unit;

use FsControl\Configuration\Binding;
use FsControl\Configuration\Configuration;
use FsControl\Configuration\Rule;
use FsControl\Core\Application;
use FsControl\Core\Result;
use FsControl\Exception\ConfigurationLoaderException;
use FsControl\Loader\DirectoryTreeLoader;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Throwable;

use function array_column;
use function sort;
use function str_replace;

/**
 * @covers \FsControl\Configuration\Binding
 * @covers \FsControl\Configuration\BindingMatch
 * @covers \FsControl\Configuration\Configuration
 * @covers \FsControl\Core\Application
 * @covers \FsControl\Loader\DirectoryTreeLoader
 */
class WildcardBindingTest extends TestCase
{
    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldTransparentlyCoverSubdomainsAtAnyDepthIncludingNewFolders(): void
    {
        $result = $this->analyze(
            [
                'Domain' => [
                    'Entity' => [],
                    'Foo' => [
                        'Entity' => [],
                    ],
                    'Sub' => [
                        'Deep' => [
                            'Entity' => [],
                        ],
                    ],
                ],
            ],
            [new Binding('$/Domain/**', 'Domain/**', 'Domain')],
            ['Entity' => ['Domain']],
        );

        self::assertSame(
            ['Domain/Entity', 'Domain/Foo/Entity', 'Domain/Sub/Deep/Entity'],
            $this->relativePaths($result->getAllowedPaths()),
        );
        self::assertSame(
            ['Domain', 'Domain/Foo', 'Domain/Sub', 'Domain/Sub/Deep'],
            $this->relativePaths($result->getBoundedPaths()),
        );
        self::assertSame(0, $result->getUncoveredPathCount());
        self::assertSame(0, $result->getUnboundedPathCount());
        self::assertSame(0, $result->getViolationPathCount());
    }

    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldTreatSingleStarAsExactlyOneGroupingLevel(): void
    {
        $result = $this->analyze(
            [
                'Domain' => [
                    'TeamA' => [
                        'Entity' => [],
                    ],
                    'TeamB' => [
                        'Nested' => [
                            'Entity' => [],
                        ],
                    ],
                ],
            ],
            [new Binding('$/Domain/*', 'Domain/*', 'Domain')],
            ['Entity' => ['Domain']],
        );

        self::assertSame(
            ['Domain/TeamA/Entity'],
            $this->relativePaths($result->getAllowedPaths()),
        );
        self::assertSame(
            ['Domain/TeamB/Nested', 'Domain/TeamB/Nested/Entity'],
            $this->relativePaths($result->getUncoveredPaths()),
        );
        self::assertSame(
            ['Domain', 'Domain/TeamA', 'Domain/TeamB'],
            $this->relativePaths($result->getBoundedPaths()),
        );
        self::assertSame(0, $result->getUnboundedPathCount());
        self::assertSame(0, $result->getViolationPathCount());
    }

    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldLetAMoreSpecificLiteralBindingWinOverAWildcard(): void
    {
        $result = $this->analyze(
            [
                'Domain' => [
                    'Foo' => [
                        'Entity' => [],
                    ],
                    'CQRS' => [
                        'Command' => [],
                    ],
                ],
            ],
            // declared wildcard-first on purpose: longest concrete mount must still win
            [
                new Binding('$/Domain/**', 'Domain/**', 'Domain'),
                new Binding('$/Domain/CQRS', 'Domain/CQRS', 'CQRS'),
            ],
            ['Entity' => ['Domain'], 'Command' => ['CQRS']],
            ['Domain', 'CQRS'],
        );

        self::assertSame(
            ['Domain/CQRS/Command', 'Domain/Foo/Entity'],
            $this->relativePaths($result->getAllowedPaths()),
        );
        self::assertSame(0, $result->getUncoveredPathCount());
        self::assertSame(0, $result->getUnboundedPathCount());
        self::assertSame(0, $result->getViolationPathCount());
    }

    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldStillDenyNestedRulesUnderAWildcardMount(): void
    {
        $result = $this->analyze(
            [
                'Domain' => [
                    'Sub' => [
                        'Entity' => [
                            'Repository' => [],
                        ],
                    ],
                ],
            ],
            [new Binding('$/Domain/**', 'Domain/**', 'Domain')],
            ['Entity' => ['Domain'], 'Repository' => ['Domain']],
            ['Domain'],
            ['deny_nested_rules' => true],
        );

        self::assertSame(
            ['Domain/Sub/Entity/Repository'],
            $this->relativePaths($result->getViolationPaths()),
        );
    }

    /**
     * @test
     *
     * @dataProvider validTailWildcardBindingProvider
     */
    public function itShouldAcceptOnlyWholeTailWildcards(string $bindingPath, string $resolvedBindingPath): void
    {
        $binding = new Binding($bindingPath, $resolvedBindingPath, 'Domain');

        self::assertSame('Domain', $binding->getGroup());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public function validTailWildcardBindingProvider(): iterable
    {
        yield 'no wildcard'      => ['$/Domain', 'Domain'];
        yield 'literal nested'   => ['$/Domain/CQRS', 'Domain/CQRS'];
        yield 'tail single star' => ['$/Domain/*', 'Domain/*'];
        yield 'tail double star' => ['$/Domain/**', 'Domain/**'];
        yield 'deep tail star'   => ['$/Domain/Sub/**', 'Domain/Sub/**'];
    }

    /**
     * @test
     *
     * @dataProvider invalidWildcardBindingProvider
     */
    public function itShouldRejectWildcardsThatAreNotAWholeTailSegment(
        string $bindingPath,
        string $resolvedBindingPath,
    ): void {
        $this->expectException(ConfigurationLoaderException::class);

        new Binding($bindingPath, $resolvedBindingPath, 'Domain');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public function invalidWildcardBindingProvider(): iterable
    {
        yield 'leading single star' => ['$/*/Domain/**', '*/Domain/**'];
        yield 'middle single star'  => ['$/Domain/*/Entity', 'Domain/*/Entity'];
        yield 'middle double star'  => ['$/**/Doc', '**/Doc'];
        yield 'partial suffix star' => ['$/Domain/Foo*', 'Domain/Foo*'];
        yield 'partial prefix star' => ['$/Domain/*Foo', 'Domain/*Foo'];
        yield 'embedded star'       => ['$/Domain/a*b', 'Domain/a*b'];
        yield 'triple star'         => ['$/Domain/***', 'Domain/***'];
    }

    /**
     * @param array<string, mixed> $tree
     * @param Binding[] $bindings
     * @param array<string, string[]> $rules
     * @param string[] $groups
     * @param array<string, scalar|null> $parameters
     *
     * @throws Throwable
     */
    private function analyze(
        array $tree,
        array $bindings,
        array $rules,
        array $groups = ['Domain'],
        array $parameters = [],
    ): Result {
        $fs = vfsStream::setup('example', 444, $tree);

        $configuration = new Configuration('test-config', []);
        $configuration->addPath($fs->url());
        foreach ($groups as $group) {
            $configuration->addGroup($group);
        }
        foreach ($bindings as $binding) {
            $configuration->addBinding($binding);
        }
        foreach ($rules as $name => $ruleGroups) {
            $configuration->addRule(new Rule($name, $ruleGroups));
        }
        foreach ($parameters as $name => $value) {
            $configuration->addParameter($name, $value);
        }

        $application = new Application(new DirectoryTreeLoader([]), $configuration);

        return $application->run();
    }

    /**
     * @param array{path: string, description?: string, reason?: string}[] $entries
     *
     * @return string[]
     */
    private function relativePaths(array $entries): array
    {
        $paths = array_column($entries, 'path');
        $paths = str_replace('vfs://example/', '', $paths);
        sort($paths);
        return $paths;
    }
}
