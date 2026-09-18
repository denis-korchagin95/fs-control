<?php

declare(strict_types=1);

namespace FsControl\Test\Unit;

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
class SkipEmptyDirectoriesTest extends TestCase
{
    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldReportEmptyDirectoriesWhenTheParameterIsNotSet(): void
    {
        $result = $this->runApplication(null);

        self::assertSame(
            ['vfs://example/Domain/Holder', 'vfs://example/Domain/Holder/Inner', 'vfs://example/Domain/Vacant'],
            $this->pathsOf($result->getUncoveredPaths()),
        );
        self::assertSame(['vfs://example/Domain/Entity'], $this->pathsOf($result->getAllowedPaths()));
    }

    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldReportEmptyDirectoriesWhenTheParameterIsDisabled(): void
    {
        $result = $this->runApplication(false);

        self::assertSame(3, $result->getUncoveredPathCount());
    }

    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldSkipOnlyTrulyEmptyDirectoriesWhenTheParameterIsEnabled(): void
    {
        $result = $this->runApplication(true);

        // "Vacant" and "Holder/Inner" hold nothing at all and became invisible, while "Holder"
        // is still reported: a directory holding only empty subdirectories is not itself empty.
        self::assertSame(['vfs://example/Domain/Holder'], $this->pathsOf($result->getUncoveredPaths()));

        // the analyzed layer is untouched: the mount point and the rule directory holding a file
        self::assertSame(1, $result->getBoundedPathCount());
        self::assertSame(['vfs://example/Domain/Entity'], $this->pathsOf($result->getAllowedPaths()));

        self::assertSame(0, $result->getViolationPathCount());
        self::assertSame(0, $result->getExcludedDirCount());
        self::assertSame(0, $result->getExcludedPathCount());
    }

    /**
     * @throws Throwable
     */
    private function runApplication(?bool $skipEmptyDirectories): Result
    {
        $fs = vfsStream::setup(
            'example',
            444,
            [
                'Domain' => [
                    'Entity' => [
                        'SomeEntity.php' => '<?php',
                    ],
                    'Vacant' => [],
                    'Holder' => [
                        'Inner' => [],
                    ],
                ],
            ],
        );

        $configuration = new Configuration('test-config', []);
        $configuration->addPath($fs->url());
        $configuration->addGroup('Domain');
        $configuration->addBinding(new Binding('$/Domain', 'Domain', 'Domain'));
        $configuration->addRule(new Rule('Entity', ['Domain']));
        if ($skipEmptyDirectories !== null) {
            $configuration->addParameter('skip_empty_directories', $skipEmptyDirectories);
        }

        return (new Application(new DirectoryTreeLoader([]), $configuration))->run();
    }

    /**
     * @param array<array{path: string, description: string}> $paths
     * @return string[]
     */
    private function pathsOf(array $paths): array
    {
        $result = array_map(static fn (array $info): string => $info['path'], $paths);
        sort($result);
        return $result;
    }
}
