<?php

declare(strict_types=1);

namespace Stolt\LlmsTxt\Tests;

use PHPUnit\Framework\Attributes\Test;
use Stolt\LlmsTxt\LlmsTxt;
use Stolt\LlmsTxt\Section;
use Stolt\LlmsTxt\Section\Link;
use Stolt\LlmsTxt\Validation\ValidationError;

final class LlmsTxtTest extends TestCase
{
    #[Test]
    public function itCreatesLlmsTxtContentInScriptContainer(): void
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
            ->asScriptTag();
        $expectedLlmsTxt = <<<LLMS_TXT_MD
<script type="text/llms.txt">
# Test title

> Test description

Test details

## Test section

- [Test link](https://llms-txt.org)

</script>

LLMS_TXT_MD;

        $this->assertSame($expectedLlmsTxt, $llmsTxt);
    }

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
    public function itValidatesLlmsTxtWithAValidationResultAsExpected(): void
    {
        $llmsTxt = new LlmsTxt();
        $llmsTxt = $llmsTxt->parse(\realpath(__DIR__ . '/fixtures/uv.llms.md'));

        $validationResult = $llmsTxt->validate(true);
        $this->assertTrue($validationResult->isValid());
        $this->assertFalse($validationResult->hasWarnings());
        $this->assertCount(0, $validationResult->warnings());
    }

    #[Test]
    public function itWarnsAboutMissingRecommendedElements(): void
    {
        $llmsTxt = new LlmsTxt();
        $llmsTxt = $llmsTxt->parse(\realpath(__DIR__ . '/fixtures/title-only.md'));

        $validationResult = $llmsTxt->validate(true);

        $this->assertTrue($validationResult->isValid());
        $this->assertCount(0, $validationResult->errors());
        $this->assertTrue($validationResult->hasWarnings());
        $this->assertCount(4, $validationResult->warnings());
        $this->assertSame(
            [
                'Missing description',
                'Missing details',
                'Missing at least one section',
                'Missing at least one section link',
            ],
            \array_map(
                static fn (ValidationError $warning): string => $warning->message(),
                $validationResult->warnings()
            )
        );
    }

    #[Test]
    public function itInvalidatesALlmsTxtWithoutATitle(): void
    {
        $llmsTxt = new LlmsTxt();
        $llmsTxt = $llmsTxt->parse(\realpath(__DIR__ . '/fixtures/without-title.md'));

        $this->assertFalse($llmsTxt->validate());

        $validationResult = $llmsTxt->validate(true);

        $this->assertFalse($validationResult->isValid());
        $this->assertCount(1, $validationResult->errors());
        $this->assertSame('Missing title', $validationResult->errors()[0]->message());
        $this->assertFalse($validationResult->hasWarnings());
    }

    #[Test]
    public function itValidatesALlmsTxtWithOnlyATitle(): void
    {
        $llmsTxt = new LlmsTxt();
        $llmsTxt = $llmsTxt->parse(\realpath(__DIR__ . '/fixtures/title-only.md'));

        $this->assertTrue($llmsTxt->validate());
    }

    #[Test]
    public function itFindsSectionLinksBeyondTheFirstSection(): void
    {
        $llmsTxt = (new LlmsTxt())->parse(<<<LLMS_TXT_MD
        # Title

        > Description

        Details

        ## Without links

        ## With a link

        - [Link title](https://link_url)
        LLMS_TXT_MD);

        $validationResult = $llmsTxt->validate(true);

        $this->assertTrue($validationResult->isValid());
        $this->assertFalse($validationResult->hasWarnings());
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

    #[Test]
    public function setsAndGetsTheOptionalSectionAsExpected(): void
    {
        $optionalSection = (new Section())->addLink(
            (new Link())->urlTitle('Secondary link')
                ->url('https://link_url')
        );

        $llmsTxt = (new LlmsTxt())->title('Test title')
            ->optional($optionalSection);

        $this->assertSame($optionalSection, $llmsTxt->getOptional());
        $this->assertSame(LlmsTxt::OPTIONAL_SECTION_NAME, $llmsTxt->getOptional()->getName());
        $this->assertTrue(\count($llmsTxt->getSections()) === 1);
    }

    #[Test]
    public function returnsNoOptionalSectionWhenNoneIsSet(): void
    {
        $section = (new Section())->name('Test section')
            ->addLink(
                (new Link())->urlTitle('Test link')
                    ->url('https://llms-txt.org')
            );

        $llmsTxt = (new LlmsTxt())->title('Test title')->addSection($section);

        $this->assertNull($llmsTxt->getOptional());
    }

    #[Test]
    public function namesTheOptionalSectionAsExpected(): void
    {
        $llmsTxt = (new LlmsTxt())->title('Test title')
            ->optional((new Section())->name('Secondary'));

        $this->assertNull($llmsTxt->getSectionByName('Secondary'));
        $this->assertNotNull($llmsTxt->getOptional());
    }

    #[Test]
    public function replacesAPresentOptionalSectionAsExpected(): void
    {
        $section = (new Section())->name('Test section')
            ->addLink(
                (new Link())->urlTitle('Test link')
                    ->url('https://llms-txt.org')
            );
        $presentOptionalSection = (new Section())->name('Optional')
            ->addLink(
                (new Link())->urlTitle('Present link')
                    ->url('https://link_url')
            );
        $replacingOptionalSection = (new Section())->addLink(
            (new Link())->urlTitle('Replacing link')
                ->url('https://link_url')
        );

        $llmsTxt = (new LlmsTxt())->title('Test title')
            ->addSection($section)
            ->addSection($presentOptionalSection)
            ->optional($replacingOptionalSection);

        $this->assertTrue(\count($llmsTxt->getSections()) === 2);
        $this->assertSame($replacingOptionalSection, $llmsTxt->getOptional());
        $this->assertSame('Replacing link', $llmsTxt->getOptional()->getLinks()[0]->getUrlTitle());
        $this->assertSame($section, $llmsTxt->getSections()[0]);
    }

    #[Test]
    public function getsTheOptionalSectionOfAParsedLlmsTxtAsExpected(): void
    {
        $llmsTxt = (new LlmsTxt())->parse(\realpath(__DIR__ . '/fixtures/example.md'));

        $optionalSection = $llmsTxt->getOptional();

        $this->assertNotNull($optionalSection);
        $this->assertTrue(\count($optionalSection->getLinks()) === 1);
        $this->assertSame('Link title', $optionalSection->getLinks()[0]->getUrlTitle());
    }

    #[Test]
    public function createsLlmsTxtContentWithAnOptionalSection(): void
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
            ->optional((new Section())->addLink(
                (new Link())->urlTitle('Secondary link')
                    ->url('https://llms-txt.org/changes.html')
            ))
            ->toString();
        $expectedLlmsTxt = <<<LLMS_TXT_MD
# Test title

> Test description

Test details

## Test section

- [Test link](https://llms-txt.org)

## Optional

- [Secondary link](https://llms-txt.org/changes.html)

LLMS_TXT_MD;

        $this->assertSame($expectedLlmsTxt, $llmsTxt);
    }
}
