<?php

declare(strict_types=1);

namespace FsControl\Test\Unit;

use FsControl\Exception\IgnoreFileException;
use FsControl\Loader\IgnoreFileLoader;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * @covers \FsControl\Loader\IgnoreFileLoader
 * @covers \FsControl\Configuration\IgnoreRules
 */
class IgnoreFileLoaderTest extends TestCase
{
    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldParsePatternsSkippingCommentsAndBlankLines(): void
    {
        $content = <<<IGNORE
        # a comment
        vendor

        Generated/
          ./build/
        src/Cache
        src/Snapshots/
        *.cache
        IGNORE;

        $fs = vfsStream::setup('example', null, ['.fs-control-ignore' => $content]);

        $ignoreRules = (new IgnoreFileLoader())->loadFromFile($fs->url() . '/.fs-control-ignore');

        // trailing "/" => subtree; bare names get the any-depth "**/" prefix, paths stay anchored
        self::assertSame(
            ['**/Generated', '**/build', 'src/Snapshots'],
            $ignoreRules->getSubtreeGlobs(),
        );
        self::assertSame(
            ['**/vendor', 'src/Cache', '**/*.cache'],
            $ignoreRules->getExactGlobs(),
        );
    }

    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldThrowWhenTheExplicitIgnoreFileCannotBeRead(): void
    {
        $this->expectException(IgnoreFileException::class);

        (new IgnoreFileLoader())->loadFromFile('vfs://example/does-not-exist');
    }
}
