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
use FsControl\Core\Application;
use FsControl\Core\PathNormalizer;
use FsControl\Loader\ConfigurationLoader;
use FsControl\Loader\DirectoryTreeLoader;
use PHPUnit\Framework\TestCase;

use function fopen;
use function is_dir;
use function mkdir;
use function rewind;
use function rmdir;
use function scandir;
use function stream_get_contents;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * @covers \FsControl\BuiltInExtension\InterfaceOnlyChecker\Extension
 * @covers \FsControl\Core\Application
 */
class InterfaceOnlyCheckerTest extends TestCase
{
    private const CATEGORY = 'interface_only_checker:not_interface';

    private string $baseDir;

    protected function setUp(): void
    {
        $baseDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('fs-control-iface-', true);
        mkdir($baseDir . '/src/Domain/Repository', 0777, true);
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
    public function itShouldFlagConcreteTypesAndReportThemWithoutABaseline(): void
    {
        $this->writeRepositoryFile('FooRepository.php', 'interface FooRepository {}');
        $this->writeRepositoryFile('BarRepository.php', 'final class BarRepository {}');
        $this->writeConfig('Domain');

        $application = $this->buildApplication(null);
        $application->run();

        [$succeeded, $output] = $this->terminate($application);

        self::assertFalse($succeeded);
        self::assertStringContainsString('BarRepository.php is not an interface', $output);
        self::assertStringNotContainsString('FooRepository.php', $output);
        self::assertSame(
            [self::CATEGORY => ['src/Domain/Repository/BarRepository.php']],
            $application->collectExtensionBaselineFindings(),
        );
    }

    /**
     * @test
     */
    public function itShouldSuppressABaselinedConcreteType(): void
    {
        $this->writeRepositoryFile('FooRepository.php', 'interface FooRepository {}');
        $this->writeRepositoryFile('BarRepository.php', 'final class BarRepository {}');
        $this->writeConfig('Domain');

        $baseline = Baseline::loadFromFile($this->writeBaseline('src/Domain/Repository/BarRepository.php'));
        $application = $this->buildApplication($baseline);
        $application->run();

        [$succeeded, $output] = $this->terminate($application);

        self::assertTrue($succeeded);
        self::assertSame('', $output);
    }

    /**
     * @test
     */
    public function itShouldNotEnforceWhenTheScopeDoesNotMatchTheGroup(): void
    {
        $this->writeRepositoryFile('BarRepository.php', 'final class BarRepository {}');
        // the dir is matched under "Domain", but enforcement is scoped to "Infrastructure"
        $this->writeConfig('Infrastructure');

        $application = $this->buildApplication(null);
        $application->run();

        [$succeeded] = $this->terminate($application);

        self::assertTrue($succeeded);
        self::assertSame([], $application->collectExtensionBaselineFindings());
    }

    /**
     * @test
     */
    public function itShouldFlagEnumsAndTraitsButIgnoreInterfacesAndClassConstants(): void
    {
        $this->writeRepositoryFile('Contract.php', 'interface Contract {}');
        $this->writeRepositoryFile(
            'WithClassConst.php',
            'interface WithClassConst { public const C = \\stdClass::class; }',
        );
        $this->writeRepositoryFile('Concrete.php', 'final class Concrete {}');
        $this->writeRepositoryFile('Color.php', 'enum Color {}');
        $this->writeRepositoryFile('Mixin.php', 'trait Mixin {}');
        $this->writeConfig('Domain');

        $application = $this->buildApplication(null);
        $application->run();

        self::assertSame(
            [
                self::CATEGORY => [
                    'src/Domain/Repository/Color.php',
                    'src/Domain/Repository/Concrete.php',
                    'src/Domain/Repository/Mixin.php',
                ],
            ],
            $application->collectExtensionBaselineFindings(),
        );
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

    private function writeRepositoryFile(string $name, string $declaration): void
    {
        file_put_contents(
            $this->baseDir . '/src/Domain/Repository/' . $name,
            '<?php' . PHP_EOL . PHP_EOL . $declaration . PHP_EOL,
        );
    }

    private function writeConfig(string $interfaceOnlyScope): void
    {
        file_put_contents($this->baseDir . '/config.yaml', <<<YAML
        fs_control:
          extensions:
            - FsControl\\BuiltInExtension\\InterfaceOnlyChecker\\Extension
          paths:
            - {$this->baseDir}/src
          groups:
            Domain: ~
          bindings:
            \$/Domain/**: Domain
          rule_attributes:
            Repository: { interface_only: {$interfaceOnlyScope} }
          rules:
            Repository:
              - Domain
        YAML);
    }

    private function writeBaseline(string $fileIdentity): string
    {
        $path = $this->baseDir . '/baseline.yaml';
        file_put_contents(
            $path,
            "extensions:\n    interface_only_checker:\n        not_interface:\n            - " . $fileIdentity . "\n",
        );
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
