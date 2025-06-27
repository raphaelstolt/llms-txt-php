<?php

declare(strict_types=1);

namespace Stolt\LlmsTxt\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stolt\LlmsTxt\LlmsTxt;
use Stolt\LlmsTxt\Section;
use Stolt\LlmsTxt\Section\Link;

final class LlmsTxtTest extends TestCase
{
    private string $temporaryDirectory = '';

    #[Test]
    public function itCreatesLlmsTxtContent(): void
    {
        $section = (new Section())->name('Test section')
            ->addLink(
                (new Link())->urlTitle('Test link')
                    ->url('https://llms-txt.org')
            );
        $llmsTxt = (new LlmsTxt())->title('Test title')
              ->description('Test description')
              ->details('Test details')
              ->addSection($section)
              ->toString();
        $expectedLlmsTxt = <<<LLMS_TXT_MD
# Test title

> Test description

Test details

## Test section

- [Test link](https://llms-txt.org)

LLMS_TXT_MD;

        $this->assertSame($expectedLlmsTxt, $llmsTxt);
    }

    #[Test]
    public function itCreatesExpectedLlmsTxtFile(): void
    {
        $this->setUpTemporaryDirectory();

        $section1 = (new Section())->name('Section name')
            ->addLink(
                (new Link())->urlTitle('Link title')
                    ->url('https://link_url')->urlDetails('Optional link details')
            );
        $section2 = (new Section())->name('Optional')
            ->addLink(
                (new Link())->urlTitle('Link title')
                    ->url('https://link_url')
            );


        (new LlmsTxt())->title('Title')
            ->description('Optional description goes here')
            ->details('Optional details go here')
            ->addSection($section1)->addSection($section2)
            ->toFile($this->temporaryDirectory . DIRECTORY_SEPARATOR . 'llms-txt.md');

        $this->assertFileEquals(__DIR__ . DIRECTORY_SEPARATOR . 'fixtures/example.md', $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'llms-txt.md');
    }

    #[Test]
    public function itParsesALlmsTxtAsExpected(): void
    {
        $llmsTxt = new LlmsTxt();
        $llmsTxt = $llmsTxt->parse(\realpath(__DIR__ . '/fixtures/example.md'));

        $expectedLlmsTxt = (new LlmsTxt())->title('Title')->description('Optional description goes here')->details('Optional details go here')
            ->addSection((new Section())->name('Section name')->addLink((new Link())->urlTitle('Link title')->url('https://link_url')->urlDetails('Optional link details')))
            ->addSection((new Section())->name('Optional')->addLink((new Link())->urlTitle('Link title')->url('https://link_url')));

        $this->assertSame($llmsTxt->toString(), $expectedLlmsTxt->toString());
    }

    #[Test]
    public function itParsesUvsLlmsTxtAsExpected(): void
    {
        $llmsTxt = new LlmsTxt();
        $llmsTxt = $llmsTxt->parse(\realpath(__DIR__ . '/fixtures/uv.llms.md'));

        $expectedDescription = <<<UV_LLMS_TXT_MD
You can use uv to install Python dependencies, run scripts, manage virtual environments, build and publish packages, and even install Python itself. uv is capable of replacing `pip`, `pip-tools`, `pipx`, `poetry`, `pyenv`, `twine`, `virtualenv`, and more. uv includes both a pip-compatible CLI (prepend `uv` to a pip command, e.g., `uv pip install ruff`) and a first-class project interface (e.g., `uv add ruff`) complete with lockfiles and workspace support.
UV_LLMS_TXT_MD;

        $this->assertTrue(\count($llmsTxt->getSections()) === 7);
        $this->assertEquals('uv', $llmsTxt->getTitle());
        $this->assertEquals($expectedDescription, $llmsTxt->getDetails());
    }

    #[Test]
    public function itValidatesLlmsTxtAsExpected(): void
    {
        $llmsTxt = new LlmsTxt();
        $llmsTxt = $llmsTxt->parse(\realpath(__DIR__ . '/fixtures/uv.llms.md'));

        $this->assertTrue($llmsTxt->validate());
    }

    #[Test]
    public function itThrowsExpectedExceptionWhenNotParsedBeforeValidating(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("The llms.txt file hasn't been parsed yet");

        (new LlmsTxt())->validate();
    }

    #[Test]
    public function guardsOnlySectionAdditions(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Sections must only include instances of Section');

        $mixedSections = [new Section(), new Link()];

        (new LlmsTxt())->addSections($mixedSections);
    }

    #[Test]
    public function addsSectionsAsExpected(): void
    {
        $mixedSections = [new Section(), new Section()];

        $llmsTxt = (new LlmsTxt())->addSections($mixedSections);

        $this->assertTrue(\count($llmsTxt->getSections()) === 2);
    }

    protected function setUpTemporaryDirectory(): void
    {
        if ($this->isWindows() === false) {
            \ini_set('sys_temp_dir', '/tmp/llms');
            $this->temporaryDirectory = '/tmp/llms';
        } else {
            $this->temporaryDirectory = \sys_get_temp_dir()
                . DIRECTORY_SEPARATOR
                . 'llms';
        }

        if (!\file_exists($this->temporaryDirectory)) {
            \mkdir($this->temporaryDirectory);
        }
    }

    protected function isWindows(string $os = PHP_OS): bool
    {
        if (\strtoupper(\substr($os, 0, 3)) !== 'WIN') {
            return false;
        }

        return true;
    }
}
