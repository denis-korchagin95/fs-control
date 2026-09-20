<?php

declare(strict_types=1);

namespace FsControl\Test\Feature;

use FsControl\Configuration\Binding;
use FsControl\Configuration\Configuration;
use FsControl\Configuration\Rule;
use FsControl\Core\Application;
use FsControl\Core\Result;
use FsControl\Loader\DirectoryTreeLoader;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * @covers \FsControl\Core\Application
 * @covers \FsControl\Core\Result
 * @covers \FsControl\Configuration\Configuration
 * @covers \FsControl\Configuration\Rule
 * @covers \FsControl\Loader\DirectoryTreeLoader
 */
class RuleAliasesTest extends TestCase
{
    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldAllowAnAliasExactlyLikeTheRuleName(): void
    {
        $result = $this->runApplication();

        self::assertSame(
            ['vfs://example/Domain/Repo', 'vfs://example/Domain/Repository'],
            $this->pathsOf($result->getAllowedPaths()),
        );
        self::assertSame(
            'The path is allowed by rules (the alias "Repo" of the rule "Repository")',
            $result->getAllowedPaths()[1]['description'],
        );
    }

    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldReportADeprecatedAliasApartFromTheAllowedPaths(): void
    {
        $result = $this->runApplication();

        self::assertSame(['vfs://example/Domain/Manager'], $this->pathsOf($result->getDeprecatedPaths()));
        self::assertSame(
            'The path uses the deprecated name "Manager" of the rule "Repository"',
            $result->getDeprecatedPaths()[0]['reason'],
        );

        // it is covered, so the directory itself is neither uncovered nor allowed
        self::assertNotContains('vfs://example/Domain/Manager', $this->pathsOf($result->getUncoveredPaths()));
        self::assertNotContains('vfs://example/Domain/Manager', $this->pathsOf($result->getAllowedPaths()));
    }

    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldReportOnlyTheRuleDirectoryItselfAsDeprecated(): void
    {
        $result = $this->runApplication();

        // "Manager/Nested" is judged by the rule as usual, so one rename is one finding
        self::assertSame(['vfs://example/Domain/Manager'], $this->pathsOf($result->getDeprecatedPaths()));
        self::assertContains('vfs://example/Domain/Manager/Nested', $this->pathsOf($result->getUncoveredPaths()));
    }

    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldPreferTheViolationWhenADeprecatedAliasSitsInAForeignGroup(): void
    {
        $result = $this->runApplication();

        // "Infrastructure/Mapper" resolves to the "Repository" rule, permitted in "Domain" only
        self::assertSame(['vfs://example/Infrastructure/Mapper'], $this->pathsOf($result->getViolationPaths()));
        self::assertNotContains('vfs://example/Infrastructure/Mapper', $this->pathsOf($result->getDeprecatedPaths()));
    }

    /**
     * @throws Throwable
     */
    private function runApplication(): Result
    {
        $fs = vfsStream::setup(
            'example',
            444,
            [
                'Domain' => [
                    'Repository' => [],            // the rule's own name
                    'Repo' => [],                  // an allowed alias
                    'Manager' => [                 // a deprecated alias
                        'Nested' => [],
                    ],
                ],
                'Infrastructure' => [
                    'Mapper' => [],                // a deprecated alias in a foreign group
                ],
            ],
        );

        $configuration = new Configuration('test-config', []);
        $configuration->addPath($fs->url());
        $configuration->addGroup('Domain');
        $configuration->addGroup('Infrastructure');
        $configuration->addBinding(new Binding('$/Domain', 'Domain', 'Domain'));
        $configuration->addBinding(new Binding('$/Infrastructure', 'Infrastructure', 'Infrastructure'));
        $configuration->addRule(new Rule('Repository', ['Domain'], ['Repo'], ['Manager', 'Mapper']));

        return (new Application(new DirectoryTreeLoader([]), $configuration))->run();
    }

    /**
     * @param array<array{path: string, description: string}|array{path: string, reason: string}> $paths
     * @return string[]
     */
    private function pathsOf(array $paths): array
    {
        $result = array_map(static fn (array $info): string => $info['path'], $paths);
        sort($result);
        return $result;
    }
}
