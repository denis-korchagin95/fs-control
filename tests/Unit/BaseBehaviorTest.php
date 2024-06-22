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

namespace FsControl\Test\Unit;

use FsControl\Configuration\Binding;
use FsControl\Configuration\Configuration;
use FsControl\Configuration\Rule;
use FsControl\Core\Application;
use FsControl\Loader\DirectoryTreeLoader;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * @covers \FsControl\Core\Application
 * @covers \FsControl\Loader\DirectoryTreeLoader
 * @covers \FsControl\Configuration\Configuration
 */
class BaseBehaviorTest extends TestCase
{
    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldNotCheckAnExcludePath(): void
    {
        $fs = vfsStream::setup(
            'example',
            444,
            [
                'Shared' => [
                    'Domain' => [
                        'Entity' => [],
                        'Check' => [
                            'SomeDir' => [],
                        ],
                    ],
                ],
            ],
        );

        $configuration = new Configuration('test-config', []);
        $configuration->addPath($fs->url());
        $configuration->addGroup('Domain');
        $configuration->addBinding(
            new Binding('$/Shared/Domain', 'Shared/Domain', 'Domain'),
        );
        $configuration->addRule(new Rule('Entity', ['Domain']));
        $configuration->addExcludePath('vfs://example/Shared/Domain/Check');
        $configuration->addExcludePath('vfs://example/Shared/Domain/Check/SomeDir');

        $application = new Application(
            new DirectoryTreeLoader([]),
            $configuration,
        );

        $result = $application->run();

        self::assertSame(
            [
                'allowedPaths' => [
                    [
                        'path' => 'vfs://example/Shared/Domain/Entity',
                        'description' => 'The path is allowed by rules',
                    ],
                ],
                'boundedPaths' => [
                    [
                        'path' => 'vfs://example/Shared',
                        'description' =>
                            'The path is the mount point of the rules applied in the config "test-config"',
                    ],
                    [
                        'path' => 'vfs://example/Shared/Domain',
                        'description' =>
                            'The path is the mount point of the rules applied in the config "test-config"',
                    ],
                ],
                'unboundedPaths' => [],
                'uncoveredPaths' => [],
                'violationPaths' => [],
            ],
            [
                'allowedPaths' => $result->getAllowedPaths(),
                'boundedPaths' => $result->getBoundedPaths(),
                'unboundedPaths' => $result->getUnboundedPaths(),
                'uncoveredPaths' => $result->getUncoveredPaths(),
                'violationPaths' => $result->getViolationPaths(),
            ],
        );
    }

    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldReportAboutUncoveredPaths(): void
    {
        $fs = vfsStream::setup(
            'example',
            444,
            [
                'Shared' => [
                    'Domain' => [
                        'Entity' => [],
                        'Check' => [
                            'SomeDir' => [],
                        ],
                    ],
                ],
            ],
        );

        $configuration = new Configuration('test-config', []);
        $configuration->addPath($fs->url());
        $configuration->addGroup('Domain');
        $configuration->addBinding(
            new Binding('$/Shared/Domain', 'Shared/Domain', 'Domain'),
        );
        $configuration->addRule(new Rule('Entity', ['Domain']));

        $application = new Application(
            new DirectoryTreeLoader([]),
            $configuration,
        );

        $result = $application->run();

        self::assertSame(
            [
                'allowedPaths' => [
                    [
                        'path' => 'vfs://example/Shared/Domain/Entity',
                        'description' => 'The path is allowed by rules',
                    ],
                ],
                'boundedPaths' => [
                    [
                        'path' => 'vfs://example/Shared',
                        'description' => 'The path is the mount point of the rules applied in the config "test-config"',
                    ],
                    [
                        'path' => 'vfs://example/Shared/Domain',
                        'description' => 'The path is the mount point of the rules applied in the config "test-config"',
                    ],
                ],
                'unboundedPaths' => [],
                'uncoveredPaths' => [
                    [
                        'path' => 'vfs://example/Shared/Domain/Check',
                        'description' => 'The path is not covered by any rules',
                    ],
                    [
                        'path' => 'vfs://example/Shared/Domain/Check/SomeDir',
                        'description' => 'The path is not covered by any rules',
                    ],
                ],
                'violationPaths' => [],
            ],
            [
                'allowedPaths' => $result->getAllowedPaths(),
                'boundedPaths' => $result->getBoundedPaths(),
                'unboundedPaths' => $result->getUnboundedPaths(),
                'uncoveredPaths' => $result->getUncoveredPaths(),
                'violationPaths' => $result->getViolationPaths(),
            ],
        );
    }

    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldReportAboutUnboundedPaths(): void
    {
        $fs = vfsStream::setup(
            'example',
            444,
            [
                'Shared' => [
                    'Domain' => [
                        'Entity' => [],
                    ],
                    'Application' => [
                        'Dto' => [],
                    ],
                ],
            ],
        );

        $configuration = new Configuration('test-config', []);
        $configuration->addPath($fs->url());
        $configuration->addGroup('Domain');
        $configuration->addBinding(
            new Binding('$/Shared/Domain', 'Shared/Domain', 'Domain'),
        );
        $configuration->addRule(new Rule('Entity', ['Domain']));

        $application = new Application(
            new DirectoryTreeLoader([]),
            $configuration,
        );

        $result = $application->run();

        self::assertSame(
            [
                'allowedPaths' => [
                    [
                        'path' => 'vfs://example/Shared/Domain/Entity',
                        'description' => 'The path is allowed by rules',
                    ],
                ],
                'boundedPaths' => [
                    [
                        'path' => 'vfs://example/Shared',
                        'description' =>
                            'The path is the mount point of the rules applied in the config "test-config"',
                    ],
                    [
                        'path' => 'vfs://example/Shared/Domain',
                        'description' =>
                            'The path is the mount point of the rules applied in the config "test-config"',
                    ],
                ],
                'unboundedPaths' => [
                    [
                        'path' => 'vfs://example/Shared/Application',
                        'reason' =>
                            'The path cannot be analyzed because no bindings configured in the config "test-config"',
                    ],
                    [
                        'path' => 'vfs://example/Shared/Application/Dto',
                        'reason' =>
                            'The path cannot be analyzed because no bindings configured in the config "test-config"',
                    ],
                ],
                'uncoveredPaths' => [],
                'violationPaths' => [],
            ],
            [
                'allowedPaths' => $result->getAllowedPaths(),
                'boundedPaths' => $result->getBoundedPaths(),
                'unboundedPaths' => $result->getUnboundedPaths(),
                'uncoveredPaths' => $result->getUncoveredPaths(),
                'violationPaths' => $result->getViolationPaths(),
            ],
        );
    }

    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldReportAboutViolationPaths(): void
    {
        $fs = vfsStream::setup(
            'example',
            444,
            [
                'Shared' => [
                    'Domain' => [
                        'Entity' => [],
                    ],
                    'Application' => [
                        'Dto' => [],
                        'Entity' => [],
                    ],
                ],
            ],
        );

        $configuration = new Configuration('test-config', []);
        $configuration->addPath($fs->url());
        $configuration->addGroup('Domain');
        $configuration->addGroup('Application');
        $configuration->addBinding(
            new Binding(
                '$/Shared/Domain',
                'Shared/Domain',
                'Domain',
            ),
        );
        $configuration->addBinding(
            new Binding(
                '$/Shared/Application',
                'Shared/Application',
                'Application',
            ),
        );
        $configuration->addRule(new Rule('Entity', ['Domain']));
        $configuration->addRule(new Rule('Dto', ['Application']));

        $application = new Application(
            new DirectoryTreeLoader([]),
            $configuration,
        );

        $result = $application->run();

        self::assertSame(
            [
                'allowedPaths' => [
                    [
                        'path' => 'vfs://example/Shared/Application/Dto',
                        'description' => 'The path is allowed by rules',
                    ],
                    [
                        'path' => 'vfs://example/Shared/Domain/Entity',
                        'description' => 'The path is allowed by rules',
                    ],
                ],
                'boundedPaths' => [
                    [
                        'path' => 'vfs://example/Shared',
                        'description' => 'The path is the mount point of the rules applied in the config "test-config"',
                    ],
                    [
                        'path' => 'vfs://example/Shared/Domain',
                        'description' => 'The path is the mount point of the rules applied in the config "test-config"',
                    ],
                    [
                        'path' => 'vfs://example/Shared/Application',
                        'description' => 'The path is the mount point of the rules applied in the config "test-config"',
                    ],
                ],
                'unboundedPaths' => [],
                'uncoveredPaths' => [],
                'violationPaths' => [
                    [
                        'path' => 'vfs://example/Shared/Application/Entity',
                        'reason' => 'The path is permitted under the "Entity" rule to be part of groups (Domain), '
                            . 'but it is located in the "Application" group',
                    ],
                ],
            ],
            [
                'allowedPaths' => $result->getAllowedPaths(),
                'boundedPaths' => $result->getBoundedPaths(),
                'unboundedPaths' => $result->getUnboundedPaths(),
                'uncoveredPaths' => $result->getUncoveredPaths(),
                'violationPaths' => $result->getViolationPaths(),
            ],
        );
    }

    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldCorrectHandleTheOverlappingTheCommonPrefixOfTheTargetDirectories(): void
    {
        $fs = vfsStream::setup(
            'example',
            444,
            [
                'Shared' => [
                    'Domain' => [
                        'Task' => [
                            'Entity' => [],
                        ],
                        'TaskManager' => [
                            'Entity' => [],
                        ],
                    ],
                ],
            ],
        );

        $configuration = new Configuration('test-config', []);
        $configuration->addPath($fs->url());
        $configuration->addGroup('Domain');
        $configuration->addGroup('Application');
        $configuration->addBinding(
            new Binding(
                '$/Shared/Domain/Task',
                'Shared/Domain/Task',
                'Domain',
            ),
        );
        $configuration->addBinding(
            new Binding(
                '$/Shared/Domain/TaskManager',
                'Shared/Domain/TaskManager',
                'Domain',
            ),
        );
        $configuration->addRule(new Rule('Entity', ['Domain']));

        $application = new Application(
            new DirectoryTreeLoader([]),
            $configuration,
        );

        $result = $application->run();

        self::assertSame(
            [
                'allowedPaths' => [
                    [
                        'path' => 'vfs://example/Shared/Domain/TaskManager/Entity',
                        'description' => 'The path is allowed by rules',
                    ],
                    [
                        'path' => 'vfs://example/Shared/Domain/Task/Entity',
                        'description' => 'The path is allowed by rules',
                    ],
                ],
                'boundedPaths' => [
                    [
                        'path' => 'vfs://example/Shared',
                        'description' => 'The path is the mount point of the rules applied in the config "test-config"',
                    ],
                    [
                        'path' => 'vfs://example/Shared/Domain',
                        'description' => 'The path is the mount point of the rules applied in the config "test-config"',
                    ],
                    [
                        'path' => 'vfs://example/Shared/Domain/Task',
                        'description' => 'The path is the mount point of the rules applied in the config "test-config"',
                    ],
                    [
                        'path' => 'vfs://example/Shared/Domain/TaskManager',
                        'description' => 'The path is the mount point of the rules applied in the config "test-config"',
                    ],
                ],
                'unboundedPaths' => [],
                'uncoveredPaths' => [],
                'violationPaths' => [],
            ],
            [
                'allowedPaths' => $result->getAllowedPaths(),
                'boundedPaths' => $result->getBoundedPaths(),
                'unboundedPaths' => $result->getUnboundedPaths(),
                'uncoveredPaths' => $result->getUncoveredPaths(),
                'violationPaths' => $result->getViolationPaths(),
            ],
        );
    }
}
