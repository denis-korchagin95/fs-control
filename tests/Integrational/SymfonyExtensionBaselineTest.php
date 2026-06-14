<?php

/*
 * This file is part of fs-control.
 *
 * (c) Denis Korchagin <denis.korchagin.1995@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FsControl\Test\Integrational;

use FsControl\Baseline\Baseline;
use FsControl\Core\PathNormalizer;
use FsControl\Core\Application;
use FsControl\Loader\ConfigurationLoader;
use FsControl\Loader\DirectoryTreeLoader;
use PHPUnit\Framework\TestCase;

use function fopen;
use function is_dir;
use function mkdir;
use function rewind;
use function rmdir;
use function scandir;
use function str_contains;
use function stream_get_contents;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * @covers \FsControl\BuiltInExtension\SymfonyExcludeServiceChecker\Extension
 * @covers \FsControl\Core\Application
 */
class SymfonyExtensionBaselineTest extends TestCase
{
    private const NOT_EXCLUDED_CATEGORY = 'symfony_exclude_service_checker:not_excluded';

    private string $baseDir;

    protected function setUp(): void
    {
        $baseDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('fs-control-ext-', true);
        mkdir($baseDir . '/config', 0777, true);
        mkdir($baseDir . '/src/Domain/Entity', 0777, true);
        mkdir($baseDir . '/src/Domain/View', 0777, true);
        $resolved = realpath($baseDir);
        self::assertNotFalse($resolved);
        $this->baseDir = $resolved;

        file_put_contents(
            $this->baseDir . '/config/services.yaml',
            "services:\n  'App\\\\':\n    resource: '../src'\n    exclude:\n      - '../src/Domain/Entity'\n",
        );
        file_put_contents($this->baseDir . '/config.yaml', $this->fsControlConfig());
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->baseDir);
    }

    /**
     * @test
     */
    public function itShouldFailAndReportTheNotExcludedPathWithoutABaseline(): void
    {
        $application = $this->buildApplication(null);
        $application->run();

        [$succeeded, $output] = $this->terminate($application);

        self::assertFalse($succeeded);
        self::assertStringContainsString('Not excluded paths', $output);
        self::assertStringContainsString('src/Domain/View', $this->relativize($output));
        self::assertSame(
            [self::NOT_EXCLUDED_CATEGORY => ['src/Domain/View']],
            $application->collectExtensionBaselineFindings(),
        );
    }

    /**
     * @test
     */
    public function itShouldSuppressABaselinedNotExcludedPath(): void
    {
        $baseline = Baseline::loadFromFile($this->writeBaseline('src/Domain/View'));
        $application = $this->buildApplication($baseline);
        $application->run();

        [$succeeded, $output] = $this->terminate($application);

        self::assertTrue($succeeded);
        self::assertSame('', $output);
    }

    private function buildApplication(?Baseline $baseline): Application
    {
        $configuration = (new ConfigurationLoader())->loadFromFile($this->baseDir . '/config.yaml');

        return new Application(
            new DirectoryTreeLoader(['.git']),
            $configuration,
            $baseline,
            new PathNormalizer($this->baseDir),
        );
    }

    /**
     * @return array{0: bool, 1: string}
     */
    private function terminate(Application $application): array
    {
        $stream = fopen('php://memory', 'r+');
        self::assertNotFalse($stream);
        $succeeded = $application->terminate($stream);
        rewind($stream);
        $output = stream_get_contents($stream);
        self::assertNotFalse($output);

        return [$succeeded, $output];
    }

    private function writeBaseline(string $notExcludedIdentity): string
    {
        $path = $this->baseDir . '/baseline.yaml';
        file_put_contents(
            $path,
            "extensions:\n    symfony_exclude_service_checker:\n        not_excluded:\n            - "
            . $notExcludedIdentity . "\n",
        );
        return $path;
    }

    private function relativize(string $output): string
    {
        return str_replace($this->baseDir . DIRECTORY_SEPARATOR, '', $output);
    }

    private function fsControlConfig(): string
    {
        return <<<YAML
        fs_control:
          extensions:
            - FsControl\\BuiltInExtension\\SymfonyExcludeServiceChecker\\Extension
          symfony_exclude_service_checker:
            configs:
              - {$this->baseDir}/config/services.yaml
          paths:
            - {$this->baseDir}/src
          groups:
            Domain: ~
          bindings:
            \$/Domain/**: Domain
          rule_attributes:
            Entity: { symfony_service: false }
            View: { symfony_service: false }
          rules:
            Entity:
              - Domain
            View:
              - Domain
        YAML;
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
