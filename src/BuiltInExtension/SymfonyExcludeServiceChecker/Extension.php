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

namespace FsControl\BuiltInExtension\SymfonyExcludeServiceChecker;

use FsControl\Core\Application;
use FsControl\Core\PathHandleContext;
use FsControl\Exception\ExtensionException;
use FsControl\Extension\ExtensionInterface;
use Symfony\Component\Yaml\Yaml;

class Extension implements ExtensionInterface
{
    private const CONFIG_KEY = 'symfony_exclude_service_checker';
    private const EXTENSION_INFO_KEY_CONFIG = self::class . ':config';
    private const EXTENSION_INFO_KEY_RESULT = self::class . ':result';

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
            $configPath = realpath($rawConfigPath);
            if ($configPath === false) {
                throw new ExtensionException(
                    self::class,
                    'Cannot resolve a symfony config path "' . $rawConfigPath . '"!',
                );
            }
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
                    throw new ExtensionException(
                        self::class,
                        'Cannot resolve a resource path "' . $resource . '"!',
                    );
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
        if ($excludePackage->isPathExcluded($context->path)) {
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
        $violations = $this->getViolationsForReport($application);
        if (count($violations) === 0) {
            return true;
        }
        fwrite($stream, PHP_EOL . PHP_EOL);
        foreach ($violations as $violation) {
            $this->reportViolationsForExcludePackage(
                $stream,
                $violation['notExcludedPaths'],
                $violation['excludePackage'],
            );
            fwrite($stream, PHP_EOL);
        }
        return false;
    }

    /**
     * @param resource $stream
     * @param string[] $notExcludePaths
     */
    private function reportViolationsForExcludePackage(
        $stream,
        array $notExcludePaths,
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
        fwrite($stream, '       Broken paths:' . PHP_EOL);
        foreach ($excludePackage->brokePaths as $path) {
            fwrite($stream, '           ' . $path . PHP_EOL);
        }
    }

    /**
     * @return array{notExcludedPaths: string[], excludePackage: ExcludePackage}[]
     */
    private function getViolationsForReport(Application $application): array
    {
        $violations = [];
        /** @var Config $config */
        $config = $application->getExtensionInfo(self::EXTENSION_INFO_KEY_CONFIG);
        foreach ($config->getExcludePackages() as $excludePackage) {
            $hash = spl_object_hash($excludePackage);
            $violations[$hash] = ['notExcludedPaths' => [], 'excludePackage' => $excludePackage];
        }
        /** @var Result|null $result */
        $result = $application->getExtensionInfo(self::EXTENSION_INFO_KEY_RESULT);
        if ($result !== null) {
            foreach ($result->getPathsGroupedByExcludePackage() as $excludePathResult) {
                $hash = spl_object_hash($excludePathResult['package']);
                if (! array_key_exists($hash, $violations)) {
                    $violations[$hash] = [
                        'notExcludedPaths' => $excludePathResult['paths'],
                        'excludePackage' => $excludePathResult['package']
                    ];
                    continue;
                }
                $violations[$hash]['notExcludedPaths'] = $excludePathResult['paths'];
            }
        }
        return array_values($violations);
    }
}
