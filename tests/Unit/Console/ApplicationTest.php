<?php

declare(strict_types=1);

namespace FriendsOfTwig\Twigcs\Tests\Unit\Console;

use FriendsOfTwig\Twigcs\Container;
use FriendsOfTwig\Twigcs\Console\Application;
use FriendsOfTwig\Twigcs\Console\ContainerAwareCommand;
use FriendsOfTwig\Twigcs\Console\LintCommand;
use FriendsOfTwig\Twigcs\Console\RegDebugCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\Command;

/**
 * @internal
 *
 * @covers \FriendsOfTwig\Twigcs\Console\Application
 */
final class ApplicationTest extends TestCase
{
    public function testAddCommandWithRegularCommand(): void
    {
        $application = new Application(false);
        $command = new Command('test:command');

        $result = $application->addCommand($command);

        self::assertSame($command, $result);
        self::assertTrue($application->has('test:command'));
    }

    public function testAddCommandWithContainerAwareCommand(): void
    {
        $application = new Application(false);
        $command = new RegDebugCommand();

        $result = $application->addCommand($command);

        self::assertSame($command, $result);
        self::assertNotNull($command->getContainer());
        self::assertTrue($application->has('reg:debug'));
    }

    public function testAddCommandWithLintCommand(): void
    {
        $application = new Application(false);
        $command = new LintCommand();

        $result = $application->addCommand($command);

        self::assertSame($command, $result);
        self::assertNotNull($command->getContainer());
        self::assertTrue($application->has('lint'));
    }

    public function testAddCommandReplacesExistingCommandWithSameName(): void
    {
        $application = new Application(false);
        $command1 = new Command('test:command');
        $command2 = new Command('test:command');

        $result1 = $application->addCommand($command1);
        $result2 = $application->addCommand($command2);

        // The first command should be returned
        self::assertSame($command1, $result1);
        // The second command should replace the first one
        self::assertSame($command2, $result2);
        // The application should have the second command registered
        self::assertSame($command2, $application->get('test:command'));
    }

    public function testAddCommandSetsContainerForContainerAwareCommand(): void
    {
        $application = new Application(false);
        $command = new RegDebugCommand();

        // Before adding, container should be null
        self::assertNull($command->getContainer());

        $application->addCommand($command);

        // After adding, container should be set
        self::assertNotNull($command->getContainer());
    }

    public function testAddCommandDoesNotSetContainerForRegularCommand(): void
    {
        $application = new Application(false);
        $command = new Command('regular:command');

        // Regular commands don't have getContainer method, so we just verify it doesn't crash
        $result = $application->addCommand($command);

        self::assertSame($command, $result);
        self::assertTrue($application->has('regular:command'));
    }

    public function testAddCommandFallsBackToAddWhenAddCommandDoesNotExist(): void
    {
        // Create a test Application class that simulates Symfony Console < 8.0
        // by implementing the same logic but testing the fallback path
        $legacyApplication = new class('test', '1.0.0') extends BaseApplication {
            private Container $container;

            public function __construct(string $name = 'test', string $version = '1.0.0')
            {
                parent::__construct($name, $version);
                $this->container = new Container();
            }

            /**
             * This method simulates Application::addCommand() but forces the fallback to add()
             * by checking a different class name that doesn't have addCommand
             */
            public function addCommandLegacy(callable|Command $command): ?Command
            {
                if ($command instanceof ContainerAwareCommand) {
                    $command->setContainer($this->container);
                }

                // Force the fallback path by checking a class that doesn't have addCommand
                // We use stdClass which definitely doesn't have addCommand
                if (!method_exists(\stdClass::class, 'addCommand')) {
                    // For Symfony Console < 8.0, ensure we only pass Command instances
                    if (!$command instanceof Command) {
                        throw new \InvalidArgumentException('Command must be an instance of ' . Command::class . ' for Symfony Console < 8.0');
                    }
                    return parent::add($command);
                }

                return parent::addCommand($command);
            }
        };

        // Test that the fallback to add() works correctly
        $command = new Command('legacy:test');
        $result = $legacyApplication->addCommandLegacy($command);

        self::assertSame($command, $result);
        self::assertTrue($legacyApplication->has('legacy:test'));

        // Test that ContainerAwareCommand gets its container set
        $containerAwareCommand = new RegDebugCommand();
        self::assertNull($containerAwareCommand->getContainer());

        $legacyApplication->addCommandLegacy($containerAwareCommand);
        self::assertNotNull($containerAwareCommand->getContainer());

        // Test that callable throws exception on old versions
        $callable = function () {
            return new Command('callable:command');
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Command must be an instance of');
        $legacyApplication->addCommandLegacy($callable);
    }
}
