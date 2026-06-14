<?php

declare(strict_types=1);

namespace FsControl\BuiltInExtension\SymfonyExcludeServiceChecker;

use FsControl\Core\Application;
use FsControl\Core\PathHandleContext;
use FsControl\Exception\ExtensionException;
use FsControl\Extension\BaselineAwareExtension;
use FsControl\Extension\ExtensionInterface;
use Symfony\Component\Yaml\Yaml;
use Webmozart\Glob\Glob;

use function str_starts_with;

class Extension implements ExtensionInterface, BaselineAwareExtension
{
    private const CONFIG_KEY = 'symfony_exclude_service_checker';
    private const EXTENSION_INFO_KEY_CONFIG = self::class . ':config';
    private const EXTENSION_INFO_KEY_RESULT = self::class . ':result';
    private const BASELINE_CATEGORY_NOT_EXCLUDED = self::CONFIG_KEY . ':not_excluded';
    private const BASELINE_CATEGORY_BROKEN = self::CONFIG_KEY . ':broken';

    /**
     * {@inheritDoc}
     */
    public function boot(Application $application): void
    {
        /**
         * @var array{
         *     fs_control?: array{
         *         symfony_exclude_service_checker?: array{
         *             configs?: string[],
         *         },
         *     },
         * } $rawConfiguration
         */
        $rawConfiguration = $application->getConfiguration()->getRawConfiguration();
        $cwd = getcwd();
        if ($cwd === false) {
            throw new ExtensionException(
                self::class,
                'Cannot fetch a current working directory!',
            );
        }
        $config = new Config();
        $application->setExtensionInfo(self::EXTENSION_INFO_KEY_CONFIG, $config);
        foreach ($rawConfiguration['fs_control'][self::CONFIG_KEY]['configs'] ?? [] as $rawConfigPath) {
            foreach ($this->resolveConfigPaths($rawConfigPath, $cwd) as $configPath) {
                $this->processConfig($config, $configPath, $cwd);
            }
        }
    }

    /**
     * Resolves a single "configs" entry to one or more Symfony config files. A "*" entry is
     * expanded as a glob (so one services.yaml per context need not be listed by hand); a
     * literal entry is resolved as before and must exist.
     *
     * @return string[]
     *
     * @throws ExtensionException
     */
    private function resolveConfigPaths(string $rawConfigPath, string $cwd): array
    {
        if (str_contains($rawConfigPath, '*')) {
            $glob = str_starts_with($rawConfigPath, DIRECTORY_SEPARATOR)
                ? $rawConfigPath
                : $cwd . DIRECTORY_SEPARATOR . $rawConfigPath;
            return Glob::glob($glob);
        }
        $configPath = realpath($rawConfigPath);
        if ($configPath === false) {
            throw new ExtensionException(
                self::class,
                'Cannot resolve a symfony config path "' . $rawConfigPath . '"!',
            );
        }
        return [$configPath];
    }

    /**
     * @throws ExtensionException
     */
    private function processConfig(Config $config, string $configPath, string $cwd): void
    {
        $configDir = dirname($configPath);
        $result = chdir($configDir);
        if ($result === false) {
            throw new ExtensionException(
                self::class,
                'Cannot change current working directory to "' . $configDir . '!"',
            );
        }
        /** @var array{
         *     services?: array<string, array{
         *         resource?: string,
         *         exclude?: string[],
         *     }>
         * } $yamlConfig
         */
        $yamlConfig = Yaml::parseFile($configPath, Yaml::PARSE_CUSTOM_TAGS);
        foreach ($yamlConfig['services'] ?? [] as $serviceName => $serviceConfig) {
            $resource = $serviceConfig['resource'] ?? null;
            if ($resource === null) {
                continue;
            }
            $resourcePath = realpath($resource);
            if ($resourcePath === false) {
                // Unresolvable resource (a glob like ".../Controller/*", a %kernel.project_dir%/...
                // parameter, or a path that no longer exists). It can never equal a scanned root,
                // so it cannot participate in the exclude check — skip it instead of aborting.
                continue;
            }
            $excludePaths = [];
            $brokePaths = [];
            foreach ($serviceConfig['exclude'] ?? [] as $excludePathPattern) {
                if (str_contains($excludePathPattern, '*')) {
                    $regexp = '/^((?:..\/)+)/';
                    if (preg_match($regexp, $excludePathPattern, $matches) === 1) {
                        $tempDir = realpath($configDir . '/' . $matches[0]);
                        if ($tempDir === false) {
                            throw new ExtensionException(
                                self::class,
                                'Cannot resolve an exclude path "' . $excludePathPattern . '"!',
                            );
                        }
                        $excludePathWithGlob = $tempDir . str_replace($matches[0], '/', $excludePathPattern);
                        $excludePaths[] = $excludePathWithGlob;
                        continue;
                    }
                    $excludePaths[] = $excludePathPattern;
                    continue;
                }
                $excludePath = realpath($excludePathPattern);
                if ($excludePath === false) {
                    $brokePaths[] = $excludePathPattern;
                    continue;
                }
                $excludePaths[] = $excludePath;
            }
            if (count($excludePaths) === 0 && count($brokePaths) === 0) {
                continue;
            }
            $excludePackage = new ExcludePackage(
                $serviceName,
                $configPath,
                $resourcePath,
                $excludePaths,
                $brokePaths,
            );
            $config->addExcludePackage($excludePackage);
        }
        $result = chdir($cwd);
        if ($result === false) {
            throw new ExtensionException(
                self::class,
                'Cannot change current working directory to "' . $cwd . '!"',
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function handle(Application $application, PathHandleContext $context): void
    {
        $rule = $context->rule;
        if ($rule === null) {
            return;
        }
        /** @var Config $config */
        $config = $application->getExtensionInfo(self::EXTENSION_INFO_KEY_CONFIG);
        $attributes = $application->getAttributesForRule($rule);
        $isSymfonyService = $attributes['symfony_service'] ?? null;
        if ($isSymfonyService === null) {
            return;
        }
        if ($isSymfonyService !== false) {
            return;
        }
        $excludePackage = $config->findExcludePackageByResourcePath($context->rootPath);
        if ($excludePackage === null) {
            return;
        }
        /** @var Result|null $result */
        $result = $application->getExtensionInfo(self::EXTENSION_INFO_KEY_RESULT);
        if ($result === null) {
            $result = new Result();
            $application->setExtensionInfo(self::EXTENSION_INFO_KEY_RESULT, $result);
        }
        if (! $excludePackage->isPathExcluded($context->path)) {
            $result->addPath(
                new NotExcludedPath($context->path, $excludePackage),
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function terminate(Application $application, $stream): bool
    {
        $violations = $this->filterBaselined($application, $this->collectViolations($application));
        if (count($violations) === 0) {
            return true;
        }
        fwrite($stream, PHP_EOL . PHP_EOL);
        foreach ($violations as $violation) {
            $this->reportViolationsForExcludePackage(
                $stream,
                $violation['notExcludedPaths'],
                $violation['brokePaths'],
                $violation['excludePackage'],
            );
            fwrite($stream, PHP_EOL);
        }
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function collectBaselineFindings(Application $application): array
    {
        $notExcluded = [];
        $broken = [];
        foreach ($this->collectViolations($application) as $violation) {
            foreach ($violation['notExcludedPaths'] as $path) {
                $notExcluded[] = $application->toProjectRelativePath($path);
            }
            foreach ($violation['brokePaths'] as $pattern) {
                $broken[] = $this->brokenIdentity($application, $violation['excludePackage'], $pattern);
            }
        }

        $findings = [];
        if ($notExcluded !== []) {
            $findings[self::BASELINE_CATEGORY_NOT_EXCLUDED] = $notExcluded;
        }
        if ($broken !== []) {
            $findings[self::BASELINE_CATEGORY_BROKEN] = $broken;
        }
        return $findings;
    }

    /**
     * Drops findings that are recorded in the baseline; a package with nothing left is removed.
     *
     * @param array{notExcludedPaths: string[], brokePaths: string[], excludePackage: ExcludePackage}[] $violations
     *
     * @return array{notExcludedPaths: string[], brokePaths: string[], excludePackage: ExcludePackage}[]
     */
    private function filterBaselined(Application $application, array $violations): array
    {
        $active = [];
        foreach ($violations as $violation) {
            $excludePackage = $violation['excludePackage'];
            $notExcludedPaths = [];
            foreach ($violation['notExcludedPaths'] as $path) {
                $identity = $application->toProjectRelativePath($path);
                if ($application->isFindingBaselined(self::BASELINE_CATEGORY_NOT_EXCLUDED, $identity)) {
                    continue;
                }
                $notExcludedPaths[] = $path;
            }
            $brokePaths = [];
            foreach ($violation['brokePaths'] as $pattern) {
                $identity = $this->brokenIdentity($application, $excludePackage, $pattern);
                if ($application->isFindingBaselined(self::BASELINE_CATEGORY_BROKEN, $identity)) {
                    continue;
                }
                $brokePaths[] = $pattern;
            }
            if ($notExcludedPaths === [] && $brokePaths === []) {
                continue;
            }
            $active[] = [
                'notExcludedPaths' => $notExcludedPaths,
                'brokePaths' => $brokePaths,
                'excludePackage' => $excludePackage,
            ];
        }
        return $active;
    }

    private function brokenIdentity(Application $application, ExcludePackage $excludePackage, string $pattern): string
    {
        return $application->toProjectRelativePath($excludePackage->configPath) . '::' . $pattern;
    }

    /**
     * @param resource $stream
     * @param string[] $notExcludePaths
     * @param string[] $brokePaths
     */
    private function reportViolationsForExcludePackage(
        $stream,
        array $notExcludePaths,
        array $brokePaths,
        ExcludePackage $excludePackage,
    ): void {
        fwrite($stream, 'Found violations for config: ' . $excludePackage->configPath . PHP_EOL);
        fwrite($stream, '   Section ' . $excludePackage->name . ':' . PHP_EOL);
        if (count($notExcludePaths) > 0) {
            fwrite($stream, '       Not excluded paths:' . PHP_EOL);
            foreach ($notExcludePaths as $path) {
                fwrite($stream, '           ' . $path . PHP_EOL);
            }
        }
        if (count($brokePaths) > 0) {
            fwrite($stream, '       Broken paths:' . PHP_EOL);
            foreach ($brokePaths as $path) {
                fwrite($stream, '           ' . $path . PHP_EOL);
            }
        }
    }

    /**
     * @return array{notExcludedPaths: string[], brokePaths: string[], excludePackage: ExcludePackage}[]
     */
    private function collectViolations(Application $application): array
    {
        $violations = [];
        /** @var Config $config */
        $config = $application->getExtensionInfo(self::EXTENSION_INFO_KEY_CONFIG);
        foreach ($config->getExcludePackages() as $excludePackage) {
            $hash = spl_object_hash($excludePackage);
            $violations[$hash] = [
                'notExcludedPaths' => [],
                'brokePaths' => $excludePackage->brokePaths,
                'excludePackage' => $excludePackage,
            ];
        }
        /** @var Result|null $result */
        $result = $application->getExtensionInfo(self::EXTENSION_INFO_KEY_RESULT);
        if ($result !== null) {
            foreach ($result->getPathsGroupedByExcludePackage() as $excludePathResult) {
                $hash = spl_object_hash($excludePathResult['package']);
                if (! array_key_exists($hash, $violations)) {
                    $violations[$hash] = [
                        'notExcludedPaths' => $excludePathResult['paths'],
                        'brokePaths' => $excludePathResult['package']->brokePaths,
                        'excludePackage' => $excludePathResult['package'],
                    ];
                    continue;
                }
                $violations[$hash]['notExcludedPaths'] = $excludePathResult['paths'];
            }
        }
        $collected = [];
        foreach ($violations as $violation) {
            if (count($violation['notExcludedPaths']) === 0 && count($violation['brokePaths']) === 0) {
                continue;
            }
            $collected[] = $violation;
        }
        return $collected;
    }
}
