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
use FsControl\Exception\DuplicateConfigurationEntryException;
use FsControl\Exception\RuleReferToUnknownGroupException;
use FsControl\Exception\WrongRuleException;
use FsControl\Loader\DirectoryTreeLoader;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FsControl\Core\Application
 * @covers \FsControl\Loader\DirectoryTreeLoader
 * @covers \FsControl\Configuration\Configuration
 */
class BaseBehaviorTest extends TestCase
{
    /**
     * @test
     * @throws DuplicateConfigurationEntryException
     * @throws RuleReferToUnknownGroupException
     * @throws WrongRuleException
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

        $configuration = new Configuration([]);
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
                    'vfs://example/Shared/Domain/Entity',
                ],
                'boundedPaths' => [
                    'vfs://example/Shared',
                    'vfs://example/Shared/Domain',
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
     * @throws DuplicateConfigurationEntryException
     * @throws RuleReferToUnknownGroupException
     * @throws WrongRuleException
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

        $configuration = new Configuration([]);
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
                    'vfs://example/Shared/Domain/Entity',
                ],
                'boundedPaths' => [
                    'vfs://example/Shared',
                    'vfs://example/Shared/Domain',
                ],
                'unboundedPaths' => [],
                'uncoveredPaths' => [
                    'vfs://example/Shared/Domain/Check',
                    'vfs://example/Shared/Domain/Check/SomeDir',
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
     * @throws DuplicateConfigurationEntryException
     * @throws RuleReferToUnknownGroupException
     * @throws WrongRuleException
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

        $configuration = new Configuration([]);
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
                    'vfs://example/Shared/Domain/Entity',
                ],
                'boundedPaths' => [
                    'vfs://example/Shared',
                    'vfs://example/Shared/Domain',
                ],
                'unboundedPaths' => [
                    'vfs://example/Shared/Application',
                    'vfs://example/Shared/Application/Dto',
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
     * @throws DuplicateConfigurationEntryException
     * @throws RuleReferToUnknownGroupException
     * @throws WrongRuleException
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

        $configuration = new Configuration([]);
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
                    'vfs://example/Shared/Application/Dto',
                    'vfs://example/Shared/Domain/Entity',
                ],
                'boundedPaths' => [
                    'vfs://example/Shared',
                    'vfs://example/Shared/Domain',
                    'vfs://example/Shared/Application',
                ],
                'unboundedPaths' => [],
                'uncoveredPaths' => [],
                'violationPaths' => [
                    'vfs://example/Shared/Application/Entity',
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
     * @throws DuplicateConfigurationEntryException
     * @throws RuleReferToUnknownGroupException
     * @throws WrongRuleException
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

        $configuration = new Configuration([]);
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
                    'vfs://example/Shared/Domain/TaskManager/Entity',
                    'vfs://example/Shared/Domain/Task/Entity',
                ],
                'boundedPaths' => [
                    'vfs://example/Shared',
                    'vfs://example/Shared/Domain',
                    'vfs://example/Shared/Domain/Task',
                    'vfs://example/Shared/Domain/TaskManager',
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
