<?php

declare(strict_types=1);

namespace FsControl\Test\Unit;

use FsControl\Configuration\Binding;
use FsControl\Configuration\Configuration;
use FsControl\Configuration\Rule;
use FsControl\Exception\DuplicateConfigurationEntryException;
use FsControl\Exception\WrongRuleException;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * @covers \FsControl\Configuration\Configuration
 * @covers \FsControl\Configuration\Rule
 */
class RuleAliasResolutionTest extends TestCase
{
    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldResolveEveryAliasToTheRuleDeclaringIt(): void
    {
        $configuration = $this->prepareConfiguration();

        foreach (['Repository', 'Repo', 'Manager'] as $name) {
            self::assertSame('Repository', $configuration->findRuleByName($name)?->getName());
        }
        self::assertNull($configuration->findRuleByName('Unknown'));
    }

    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldTellADeprecatedAliasFromAnAllowedOne(): void
    {
        $rule = $this->prepareConfiguration()->findRuleByName('Repo');

        self::assertNotNull($rule);
        self::assertTrue($rule->isDeprecatedName('Manager'));
        self::assertFalse($rule->isDeprecatedName('Repo'));
        self::assertFalse($rule->isDeprecatedName('Repository'));
    }

    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldStopTheGlobstarOnAnAliasLikeOnTheRuleName(): void
    {
        $configuration = $this->prepareConfiguration();
        $configuration->addBinding(new Binding('$/Domain/**', 'Domain/**', 'Domain'));

        // the wildcard consumes grouping folders up to the first recognized rule — an alias
        // is recognized exactly like the rule's own name
        self::assertSame('Domain/Team', $configuration->getBindingForPath('Domain/Team/Repo')?->mountPath);
        self::assertSame('Domain/Team', $configuration->getBindingForPath('Domain/Team/Manager')?->mountPath);
    }

    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldRejectAnAliasAlreadyUsedByAnotherRule(): void
    {
        $configuration = $this->prepareConfiguration();

        $this->expectException(DuplicateConfigurationEntryException::class);
        $this->expectExceptionMessage(
            'The name "Repo" of the rule "Storage" is already used by the rule "Repository"!',
        );

        $configuration->addRule(new Rule('Storage', ['Domain'], ['Repo']));
    }

    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldRejectARuleNameAlreadyUsedAsAnAlias(): void
    {
        $configuration = $this->prepareConfiguration();

        $this->expectException(DuplicateConfigurationEntryException::class);

        $configuration->addRule(new Rule('Manager', ['Domain']));
    }

    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldRejectAnAliasThatIsBothAllowedAndDeprecated(): void
    {
        $this->expectException(WrongRuleException::class);
        $this->expectExceptionMessage(
            'The alias "Repo" of the rule "Repository" cannot be allowed and deprecated at the same time!',
        );

        new Rule('Repository', ['Domain'], ['Repo'], ['Repo']);
    }

    /**
     * @test
     *
     * @throws Throwable
     */
    public function itShouldRejectAnAliasThatIsAPath(): void
    {
        $this->expectException(WrongRuleException::class);

        new Rule('Repository', ['Domain'], ['Domain/Repo']);
    }

    /**
     * @throws Throwable
     */
    private function prepareConfiguration(): Configuration
    {
        $configuration = new Configuration('test-config', []);
        $configuration->addGroup('Domain');
        $configuration->addBinding(new Binding('$/Domain', 'Domain', 'Domain'));
        $configuration->addRule(new Rule('Repository', ['Domain'], ['Repo'], ['Manager']));

        return $configuration;
    }
}
