<?php

declare(strict_types=1);

namespace Stolt\LlmsTxt\Tests\Commands;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use Stolt\LlmsTxt\Commands\InfoCommand;
use Stolt\LlmsTxt\LlmsTxt;
use Stolt\LlmsTxt\Tests\TestCase;
use Zenstruck\Console\Test\TestCommand;

final class InfoCommandTest extends TestCase
{
    #[Test]
    #[RunInSeparateProcess]
    public function returnsExpectedWarningWhenNoLlmsTxtFileIsFound(): void
    {
        $infoCommand = new InfoCommand(
            new LlmsTxt()
        );

        $expectedOutput =  <<<OUTPUT
Warning: The provided llms txt file non-existent-llms.txt does not exists.
OUTPUT;

        TestCommand::for($infoCommand)
            ->addArgument('non-existent-llms.txt')
            ->execute()
            ->assertOutputContains($expectedOutput)
            ->assertFaulty();
    }

    #[Test]
    #[RunInSeparateProcess]
    public function returnsSuccessfullyInfoMetadata(): void
    {
        $this->setUpTemporaryDirectory();

        \copy(\dirname(\dirname(__FILE__)) . '/fixtures/example.md', $this->temporaryDirectory . '/example.md');
        $this->assertTrue(\file_exists($this->temporaryDirectory . '/example.md'));
        \chdir($this->temporaryDirectory);

        $infoCommand = new InfoCommand(
            new LlmsTxt()
        );

        TestCommand::for($infoCommand)
            ->addArgument('example.md')
            ->execute()
            ->assertSuccessful();
    }
}
