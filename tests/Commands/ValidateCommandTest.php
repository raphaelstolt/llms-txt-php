<?php

declare(strict_types=1);

namespace Stolt\LlmsTxt\Tests\Commands;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use Stolt\LlmsTxt\Commands\ValidateCommand;
use Stolt\LlmsTxt\LlmsTxt;
use Stolt\LlmsTxt\Tests\TestCase;
use Zenstruck\Console\Test\TestCommand;

final class ValidateCommandTest extends TestCase
{
    #[Test]
    #[RunInSeparateProcess]
    public function detectsNonExistingLlmsTxtFile(): void
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
    public function detectsValidLlmsTxtFile(): void
    {
        $validateCommand = new ValidateCommand(
            new LlmsTxt()
        );

        $libraryLlmsTxtFile = \dirname(\dirname(\dirname(__FILE__))) . '/llms.txt';

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
    public function detectsInvalidLlmsTxtFile(): void
    {
        $validateCommand = new ValidateCommand(
            new LlmsTxt()
        );

        $libraryLlmsTxtFile = \dirname(\dirname(\dirname(__FILE__))) . '/README.md';

        $expectedOutput = <<<CONTENT
The provided llms txt file {$libraryLlmsTxtFile} is invalid.
CONTENT;

        TestCommand::for($validateCommand)
            ->addArgument($libraryLlmsTxtFile)
            ->execute()
            ->assertOutputContains($expectedOutput)
            ->assertFaulty();
    }

    #[Test]
    #[RunInSeparateProcess]
    public function validatesRemoteLlmsTxtFile(): void
    {
        $validateCommand = new ValidateCommand(
            new LlmsTxt()
        );

        $expectedOutput = <<<CONTENT
The delivered llms txt file from
CONTENT;

        TestCommand::for($validateCommand)
            ->addArgument('https://docs.astral.sh/uv/')
            ->execute()
            ->assertOutputContains($expectedOutput)
            ->assertSuccessful();
    }
}
