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
 * @covers \FsControl\Loader\DirectoryTreeLoader
 */
class TolerantModeTest extends TestCase
{
    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldReportUncoveredAndUnboundedPathsInTheStrictModeUsedByDefault(): void
    {
        $result = $this->runApplication(null);

        self::assertSame(['vfs://example/Outside'], $this->pathsOf($result->getUnboundedPaths()));
        self::assertSame(['vfs://example/Domain/Whatever'], $this->pathsOf($result->getUncoveredPaths()));
        self::assertSame(0, $result->getOutOfCoveragePathCount());

        // the defined rules are enforced as usual
        self::assertSame(['vfs://example/Infrastructure/Entity'], $this->pathsOf($result->getViolationPaths()));
        self::assertSame(['vfs://example/Domain/Entity'], $this->pathsOf($result->getAllowedPaths()));
    }

    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldMoveUncoveredAndUnboundedPathsOutOfCoverageInTheTolerantMode(): void
    {
        $result = $this->runApplication(Configuration::MODE_TOLERANT);

        self::assertSame(0, $result->getUncoveredPathCount());
        self::assertSame(0, $result->getUnboundedPathCount());
        self::assertSame(
            ['vfs://example/Domain/Whatever', 'vfs://example/Outside'],
            $this->pathsOf($result->getOutOfCoveragePaths()),
        );

        // the defined rules are still matched and tracked: a covered path breaking its rule
        // remains a violation, and a path allowed by its rule remains allowed
        self::assertSame(['vfs://example/Infrastructure/Entity'], $this->pathsOf($result->getViolationPaths()));
        self::assertSame(['vfs://example/Domain/Entity'], $this->pathsOf($result->getAllowedPaths()));
        self::assertSame(2, $result->getBoundedPathCount());
    }

    /**
     * @throws Throwable
     */
    private function runApplication(?string $mode): Result
    {
        $configuration = $this->prepareConfiguration();
        if ($mode !== null) {
            $configuration->setMode($mode);
        }

        return (new Application(new DirectoryTreeLoader([]), $configuration))->run();
    }

    /**
     * @throws Throwable
     */
    private function prepareConfiguration(): Configuration
    {
        $fs = vfsStream::setup(
            'example',
            444,
            [
                // bound to the "Domain" group, holds a rule directory and an unknown one
                'Domain' => [
                    'Entity' => [],
                    'Whatever' => [],
                ],
                // bound to the "Infrastructure" group, but "Entity" is permitted in "Domain" only
                'Infrastructure' => [
                    'Entity' => [],
                ],
                // reached by no binding at all
                'Outside' => [],
            ],
        );

        $configuration = new Configuration('test-config', []);
        $configuration->addPath($fs->url());
        $configuration->addGroup('Domain');
        $configuration->addGroup('Infrastructure');
        $configuration->addBinding(new Binding('$/Domain', 'Domain', 'Domain'));
        $configuration->addBinding(new Binding('$/Infrastructure', 'Infrastructure', 'Infrastructure'));
        $configuration->addRule(new Rule('Entity', ['Domain']));

        return $configuration;
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
