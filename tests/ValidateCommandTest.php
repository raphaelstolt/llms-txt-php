<?php

declare(strict_types=1);

namespace Stolt\LlmsTxt\Tests;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stolt\LlmsTxt\Commands\ValidateCommand;
use Stolt\LlmsTxt\LlmsTxt;
use Symfony\Component\Console\Application;
use Zenstruck\Console\Test\TestCommand;

final class ValidateCommandTest extends TestCase
{
    #[Test]
    #[RunInSeparateProcess]
    public function detectsNonExistingLlmsFile(): void
    {
        $validateCommand = new ValidateCommand(
            new LlmsTxt()
        );

        $expectedOutput = <<<CONTENT
Warning: The provided llms txt file llms-txt-file does not exists.
CONTENT;

        TestCommand::for($validateCommand)
            ->addArgument('llms-txt-file')
            ->execute()
            ->assertOutputContains($expectedOutput)
            ->assertFaulty();
    }

    #[Test]
    #[RunInSeparateProcess]
    public function detectsValidLlmsFile(): void
    {
        $validateCommand = new ValidateCommand(
            new LlmsTxt()
        );

        $libraryLlmsTxtFile = \dirname(\dirname(__FILE__)) . '/llms.txt';

        $expectedOutput = <<<CONTENT
The provided llms txt file {$libraryLlmsTxtFile} is valid.
CONTENT;

        TestCommand::for($validateCommand)
            ->addArgument($libraryLlmsTxtFile)
            ->execute()
            ->assertOutputContains($expectedOutput)
            ->assertSuccessful();
    }

    #[Test]
    #[RunInSeparateProcess]
    public function detectsInvalidLlmsFile(): void
    {
        $validateCommand = new ValidateCommand(
            new LlmsTxt()
        );

        $libraryLlmsTxtFile = \dirname(\dirname(__FILE__)) . '/composer.json';

        $expectedOutput = <<<CONTENT
The provided llms txt file {$libraryLlmsTxtFile} is invalid.
CONTENT;

        TestCommand::for($validateCommand)
            ->addArgument($libraryLlmsTxtFile)
            ->execute()
            ->assertOutputContains($expectedOutput)
            ->assertFaulty();
    }
}
