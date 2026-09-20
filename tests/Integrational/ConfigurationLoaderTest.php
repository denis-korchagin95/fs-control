<?php

declare(strict_types=1);

namespace FsControl\Test\Integrational;

use FsControl\Configuration\Configuration;
use FsControl\Configuration\Rule;
use FsControl\Exception\ConfigurationLoaderException;
use FsControl\Exception\ConfigurationModeException;
use FsControl\Loader\ConfigurationLoader;
use PHPUnit\Framework\TestCase;

use function is_dir;
use function mkdir;
use function rmdir;
use function scandir;
use function sort;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * @covers \FsControl\Loader\ConfigurationLoader
 * @covers \FsControl\Configuration\Configuration
 * @covers \FsControl\Exception\ConfigurationLoaderException
 * @covers \FsControl\Exception\ConfigurationModeException
 */
class ConfigurationLoaderTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $baseDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('fs-control-test-', true);
        mkdir($baseDir, 0777, true);
        $resolved = realpath($baseDir);
        self::assertNotFalse($resolved);
        $this->baseDir = $resolved;
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->baseDir);
    }

    /**
     * @test
     */
    public function itShouldExpandATailSingleStarPathToItsImmediateSubdirectories(): void
    {
        mkdir($this->baseDir . '/ctx/Alpha', 0777, true);
        mkdir($this->baseDir . '/ctx/Beta', 0777, true);
        // files and dot-directories must be ignored by the expansion
        file_put_contents($this->baseDir . '/ctx/note.txt', 'x');
        mkdir($this->baseDir . '/ctx/.hidden', 0777, true);

        $configuration = (new ConfigurationLoader())->loadFromFile(
            $this->writeConfig("fs_control:\n  paths:\n    - " . $this->baseDir . "/ctx/*\n"),
        );

        self::assertSame(
            [
                $this->baseDir . '/ctx/Alpha',
                $this->baseDir . '/ctx/Beta',
            ],
            $configuration->getPaths(),
        );
    }

    /**
     * @test
     */
    public function itShouldStillResolveLiteralPathsUnchanged(): void
    {
        mkdir($this->baseDir . '/ctx/Alpha', 0777, true);

        $configuration = (new ConfigurationLoader())->loadFromFile(
            $this->writeConfig("fs_control:\n  paths:\n    - " . $this->baseDir . "/ctx/Alpha\n"),
        );

        self::assertSame([$this->baseDir . '/ctx/Alpha'], $configuration->getPaths());
    }

    /**
     * @test
     */
    public function itShouldDropBroadPathsCoveredByADeeperRoot(): void
    {
        mkdir($this->baseDir . '/ctx/Foo', 0777, true);
        mkdir($this->baseDir . '/ctx/Bar', 0777, true);
        mkdir($this->baseDir . '/ctx/Module/Sub', 0777, true);
        mkdir($this->baseDir . '/ctx/Module/Other', 0777, true);

        $configuration = (new ConfigurationLoader())->loadFromFile(
            $this->writeConfig(
                "fs_control:\n  paths:\n    - " . $this->baseDir . "/ctx/*\n"
                . '    - ' . $this->baseDir . "/ctx/Module/*\n",
            ),
        );

        // "ctx/*" expands to Foo, Bar, Module; "ctx/Module/*" expands to Sub, Other.
        // The broad "Module" is dropped (covered by the deeper Module/* roots), so it is
        // not scanned twice; new siblings under ctx are still auto-picked-up by "ctx/*".
        $paths = $configuration->getPaths();
        sort($paths);
        self::assertSame(
            [
                $this->baseDir . '/ctx/Bar',
                $this->baseDir . '/ctx/Foo',
                $this->baseDir . '/ctx/Module/Other',
                $this->baseDir . '/ctx/Module/Sub',
            ],
            $paths,
        );
    }

    /**
     * @test
     */
    public function itShouldRejectADoubleStarPath(): void
    {
        mkdir($this->baseDir . '/ctx', 0777, true);

        $this->expectException(ConfigurationLoaderException::class);

        (new ConfigurationLoader())->loadFromFile(
            $this->writeConfig("fs_control:\n  paths:\n    - " . $this->baseDir . "/ctx/**\n"),
        );
    }

    /**
     * @test
     */
    public function itShouldRejectAPartialStarPath(): void
    {
        mkdir($this->baseDir . '/ctx', 0777, true);

        $this->expectException(ConfigurationLoaderException::class);

        (new ConfigurationLoader())->loadFromFile(
            $this->writeConfig("fs_control:\n  paths:\n    - " . $this->baseDir . "/ctx/Foo*\n"),
        );
    }

    /**
     * @test
     */
    public function itShouldRejectANonTailStarPath(): void
    {
        mkdir($this->baseDir . '/ctx', 0777, true);

        $this->expectException(ConfigurationLoaderException::class);

        (new ConfigurationLoader())->loadFromFile(
            $this->writeConfig("fs_control:\n  paths:\n    - " . $this->baseDir . "/*/ctx\n"),
        );
    }

    /**
     * @test
     */
    public function itShouldUseTheStrictModeWhenTheModeIsNotGiven(): void
    {
        mkdir($this->baseDir . '/ctx', 0777, true);

        $configuration = (new ConfigurationLoader())->loadFromFile(
            $this->writeConfig("fs_control:\n  paths:\n    - " . $this->baseDir . "/ctx\n"),
        );

        self::assertSame(Configuration::MODE_STRICT, $configuration->getMode());
        self::assertFalse($configuration->isTolerantMode());
    }

    /**
     * @test
     */
    public function itShouldReadTheTolerantMode(): void
    {
        mkdir($this->baseDir . '/ctx', 0777, true);

        $configuration = (new ConfigurationLoader())->loadFromFile(
            $this->writeConfig(
                "fs_control:\n  mode: tolerant\n  paths:\n    - " . $this->baseDir . "/ctx\n",
            ),
        );

        self::assertSame(Configuration::MODE_TOLERANT, $configuration->getMode());
        self::assertTrue($configuration->isTolerantMode());
    }

    /**
     * @test
     */
    public function itShouldRejectAnUnknownMode(): void
    {
        mkdir($this->baseDir . '/ctx', 0777, true);

        $this->expectException(ConfigurationModeException::class);
        $this->expectExceptionMessage('The unknown mode "whatever"! Expected one of: strict, tolerant.');

        (new ConfigurationLoader())->loadFromFile(
            $this->writeConfig(
                "fs_control:\n  mode: whatever\n  paths:\n    - " . $this->baseDir . "/ctx\n",
            ),
        );
    }

    /**
     * @test
     */
    public function itShouldRejectANonStringMode(): void
    {
        mkdir($this->baseDir . '/ctx', 0777, true);

        $this->expectException(ConfigurationLoaderException::class);
        $this->expectExceptionMessage('The "mode" should be a string!');

        (new ConfigurationLoader())->loadFromFile(
            $this->writeConfig(
                "fs_control:\n  mode: true\n  paths:\n    - " . $this->baseDir . "/ctx\n",
            ),
        );
    }

    /**
     * @test
     */
    public function itShouldLoadBothTheShortAndTheExpandedRuleForms(): void
    {
        mkdir($this->baseDir . '/ctx', 0777, true);

        $configuration = (new ConfigurationLoader())->loadFromFile(
            $this->writeConfig(
                "fs_control:\n  paths:\n    - " . $this->baseDir . "/ctx\n"
                . "  groups:\n    Domain: ~\n  bindings:\n    $/Domain: Domain\n"
                . "  rules:\n"
                . "    Entity:\n      - Domain\n"
                . "    Repository:\n      groups:\n        - Domain\n"
                . "      allowed_aliases:\n        - Repo\n"
                . "      deprecated_aliases:\n        - Manager\n",
            ),
        );

        self::assertSame('Entity', $configuration->findRuleByName('Entity')?->getName());
        self::assertSame('Repository', $configuration->findRuleByName('Repo')?->getName());
        self::assertSame('Repository', $configuration->findRuleByName('Manager')?->getName());
        $repository = $configuration->findRuleByName('Manager');
        self::assertInstanceOf(Rule::class, $repository);
        self::assertTrue($repository->isDeprecatedName('Manager'));
        self::assertSame(['Repo'], $repository->getAllowedAliases());
        self::assertTrue($configuration->hasDeprecatedAliases());
    }

    /**
     * @test
     */
    public function itShouldRejectAnUnknownKeyOfTheExpandedRuleForm(): void
    {
        mkdir($this->baseDir . '/ctx', 0777, true);

        $this->expectException(ConfigurationLoaderException::class);
        $this->expectExceptionMessage(
            'The unknown key "aliases" of the rule "Repository"!'
            . ' Expected one of: groups, allowed_aliases, deprecated_aliases.',
        );

        (new ConfigurationLoader())->loadFromFile(
            $this->writeConfig(
                "fs_control:\n  paths:\n    - " . $this->baseDir . "/ctx\n"
                . "  groups:\n    Domain: ~\n  bindings:\n    $/Domain: Domain\n"
                . "  rules:\n    Repository:\n      groups:\n        - Domain\n"
                . "      aliases:\n        - Repo\n",
            ),
        );
    }

    /**
     * @test
     */
    public function itShouldRejectTheExpandedRuleFormWithoutGroups(): void
    {
        mkdir($this->baseDir . '/ctx', 0777, true);

        $this->expectException(ConfigurationLoaderException::class);
        $this->expectExceptionMessage('The rule "Repository" should have the "groups" key!');

        (new ConfigurationLoader())->loadFromFile(
            $this->writeConfig(
                "fs_control:\n  paths:\n    - " . $this->baseDir . "/ctx\n"
                . "  groups:\n    Domain: ~\n  bindings:\n    $/Domain: Domain\n"
                . "  rules:\n    Repository:\n      allowed_aliases:\n        - Repo\n",
            ),
        );
    }

    private function writeConfig(string $contents): string
    {
        $path = $this->baseDir . '/config.yaml';
        file_put_contents($path, $contents);
        return $path;
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }
        $entries = scandir($directory);
        if ($entries === false) {
            return;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }
            unlink($path);
        }
        rmdir($directory);
    }
}
