<?php

declare(strict_types=1);

namespace Stolt\LlmsTxt\Tests\Parser;

use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Stolt\LlmsTxt\LlmsTxt;
use Stolt\LlmsTxt\Parser\LlmsTxtParser;
use Stolt\LlmsTxt\Tests\TestCase;

final class LlmsTxtParserTest extends TestCase
{
    #[Test]
    public function itParsesWindowsLineEndings(): void
    {
        $content = "# Title\r\n\r\n> Description\r\n\r\n## Docs\r\n\r\n- [Home](https://example.com)\r\n";

        $llmsTxt = (new LlmsTxtParser())->parse($content);

        self::assertSame('Title', $llmsTxt->getTitle());
        self::assertSame('Description', $llmsTxt->getDescription());
        self::assertSame('Docs', $llmsTxt->getSections()[0]->getName());
        self::assertSame(
            'https://example.com',
            $llmsTxt->getSections()[0]->getLinks()[0]->getUrl()
        );
    }

    #[Test]
    public function itParsesCarriageReturnLineEndings(): void
    {
        $content = "# Title\r\r> Description\r\r## Docs\r\r- [Home](https://example.com)\r";

        $llmsTxt = (new LlmsTxtParser())->parse($content);

        self::assertSame('Title', $llmsTxt->getTitle());
        self::assertSame('Description', $llmsTxt->getDescription());
        self::assertSame('Docs', $llmsTxt->getSections()[0]->getName());
    }

    #[Test]
    public function itParsesAByteOrderMark(): void
    {
        $content = "\xEF\xBB\xBF# Title\n";

        $llmsTxt = (new LlmsTxtParser())->parse($content);

        self::assertSame('Title', $llmsTxt->getTitle());
    }

    #[Test]
    public function itPreservesSoftLineBreaks(): void
    {
        $content = <<<MD
            # Title

            First line.
            Second line.

            ## Docs

            - [Documentation](https://example.com)
            MD;

        $llmsTxt = (new LlmsTxtParser())->parse($content);

        self::assertSame(
            "First line.\nSecond line.",
            $llmsTxt->getDetails()
        );
    }

    #[Test]
    public function itPreservesHardLineBreaks(): void
    {
        $content = "# Title\n\nFirst line.  \nSecond line.\n";

        $llmsTxt = (new LlmsTxtParser())->parse($content);

        self::assertSame(
            "First line.  \nSecond line.",
            $llmsTxt->getDetails()
        );
    }

    #[Test]
    public function itPreservesMultipleParagraphs(): void
    {
        $content = <<<MD
            # Title

            First paragraph.

            Second paragraph.

            - First item
            - Second item
            MD;

        $llmsTxt = (new LlmsTxtParser())->parse($content);

        self::assertSame(
            "First paragraph.\n\nSecond paragraph.\n\n- First item\n- Second item",
            $llmsTxt->getDetails()
        );
    }

    #[Test]
    public function itTrimsBlankLinesAroundDetails(): void
    {
        $content = "# Title\n\n> Description\n\n\n\nDetails.\n\n\n\n## Docs\n";

        $llmsTxt = (new LlmsTxtParser())->parse($content);

        self::assertSame('Details.', $llmsTxt->getDetails());
    }

    #[Test]
    public function itParsesUrlsContainingParentheses(): void
    {
        $content = <<<MD
            # Title

            ## Docs

            - [Example](https://example.com/foo(bar))
            - [Nested](https://example.com/foo(a(b)c)): With details.
            - [Escaped](https://example.com/foo\\)bar)
            MD;

        $links = (new LlmsTxtParser())->parse($content)->getSections()[0]->getLinks();

        self::assertSame('https://example.com/foo(bar)', $links[0]->getUrl());
        self::assertSame('https://example.com/foo(a(b)c)', $links[1]->getUrl());
        self::assertSame('With details.', $links[1]->getUrlDetails());
        self::assertSame('https://example.com/foo\\)bar', $links[2]->getUrl());
    }

    #[Test]
    public function itPreservesUnicode(): void
    {
        $content = <<<MD
            # Überprüfung

            > Eine Beschreibung mit ä, ö, ü und €.

            ## Dokumentation

            - [Einführung](https://example.com/einführung)
            MD;

        $llmsTxt = (new LlmsTxtParser())->parse($content);

        self::assertSame('Überprüfung', $llmsTxt->getTitle());
        self::assertSame(
            'Eine Beschreibung mit ä, ö, ü und €.',
            $llmsTxt->getDescription()
        );
        self::assertSame(
            'https://example.com/einführung',
            $llmsTxt->getSections()[0]->getLinks()[0]->getUrl()
        );
    }

    #[Test]
    public function itParsesLinkDetails(): void
    {
        $content = "# Title\n\n## Docs\n\n- [Guide](https://example.com): The guide: part two.\n";

        $link = (new LlmsTxtParser())->parse($content)->getSections()[0]->getLinks()[0];

        self::assertSame('Guide', $link->getUrlTitle());
        self::assertSame('https://example.com', $link->getUrl());
        self::assertSame('The guide: part two.', $link->getUrlDetails());
    }

    #[Test]
    public function itParsesALinkTitleHoldingAColon(): void
    {
        $content = "# Title\n\n## Docs\n\n- [Guide: part two](https://example.com): Details here.\n";

        $link = (new LlmsTxtParser())->parse($content)->getSections()[0]->getLinks()[0];

        self::assertSame('Guide: part two', $link->getUrlTitle());
        self::assertSame('https://example.com', $link->getUrl());
        self::assertSame('Details here.', $link->getUrlDetails());
    }

    #[Test]
    public function itSkipsSectionLinesWithoutALink(): void
    {
        $content = <<<MD
            # Title

            ## Docs

            Some prose that is no file list entry.

            - [Guide](https://example.com)
            - Just a list item
            - [Broken](https://example.com trailing
            MD;

        $links = (new LlmsTxtParser())->parse($content)->getSections()[0]->getLinks();

        self::assertCount(1, $links);
        self::assertSame('Guide', $links[0]->getUrlTitle());
    }

    #[Test]
    public function itMergesSectionsOfTheSameName(): void
    {
        $content = <<<MD
            # Title

            ## Docs

            - [First](https://example.com/first)

            ## Optional

            - [Third](https://example.com/third)

            ## Docs

            - [Second](https://example.com/second)
            MD;

        $llmsTxt = (new LlmsTxtParser())->parse($content);

        self::assertCount(2, $llmsTxt->getSections());

        $docs = $llmsTxt->getSectionByName('Docs');

        self::assertNotNull($docs);
        self::assertCount(2, $docs->getLinks());
        self::assertSame('First', $docs->getLinks()[0]->getUrlTitle());
        self::assertSame('Second', $docs->getLinks()[1]->getUrlTitle());
    }

    #[Test]
    public function itKeepsAFencedCodeBlockOutOfTheDocumentStructure(): void
    {
        $content = <<<MD
            # Title

            > Summary

            A llms.txt file looks like:

            ```markdown
            ## Fenced

            - [Fenced entry](https://fenced.example.com)
            ```

            Back to prose.

            ## Docs

            - [Real entry](https://example.com)
            MD;

        $llmsTxt = (new LlmsTxtParser())->parse($content);

        self::assertCount(1, $llmsTxt->getSections());
        self::assertSame('Docs', $llmsTxt->getSections()[0]->getName());
        self::assertNull($llmsTxt->getSectionByName('Fenced'));
        self::assertSame(
            "A llms.txt file looks like:\n\n```markdown\n## Fenced\n\n"
            . "- [Fenced entry](https://fenced.example.com)\n```\n\nBack to prose.",
            $llmsTxt->getDetails()
        );
    }

    #[Test]
    public function itKeepsAFencedCodeBlockOfASectionOutOfItsFileList(): void
    {
        $content = <<<MD
            # Title

            ## Docs

            - [Real entry](https://example.com)

            ~~~
            - [Fenced entry](https://fenced.example.com)
            ~~~
            MD;

        $llmsTxt = (new LlmsTxtParser())->parse($content);

        $links = $llmsTxt->getSections()[0]->getLinks();

        self::assertCount(1, $links);
        self::assertSame('Real entry', $links[0]->getUrlTitle());
    }

    #[Test]
    public function itClosesAFencedCodeBlockOnlyOnAMatchingFence(): void
    {
        $content = <<<MD
            # Title

            ````markdown
            ```
            ## Not a section
            ```
            ````

            ## Docs

            - [Real entry](https://example.com)
            MD;

        $llmsTxt = (new LlmsTxtParser())->parse($content);

        self::assertCount(1, $llmsTxt->getSections());
        self::assertSame('Docs', $llmsTxt->getSections()[0]->getName());
    }

    #[Test]
    public function itJoinsTheLinesOfAMultiLineBlockquoteSummary(): void
    {
        $content = <<<MD
            # Title

            > First line of the summary
            > second line of the summary.

            Details prose.

            ## Docs

            - [Home](https://example.com)
            MD;

        $llmsTxt = (new LlmsTxtParser())->parse($content);

        self::assertSame(
            'First line of the summary second line of the summary.',
            $llmsTxt->getDescription()
        );
        self::assertSame('Details prose.', $llmsTxt->getDetails());
    }

    #[Test]
    public function itParsesIndentedHeadingsAndBlockquotes(): void
    {
        $content = "   # Title\n\n  > Summary\n\n ## Docs\n\n- [Home](https://example.com)\n";

        $llmsTxt = (new LlmsTxtParser())->parse($content);

        self::assertSame('Title', $llmsTxt->getTitle());
        self::assertSame('Summary', $llmsTxt->getDescription());
        self::assertSame('Docs', $llmsTxt->getSections()[0]->getName());
    }

    #[Test]
    public function itStripsTheClosingSequenceOfAHeading(): void
    {
        $content = "# Title #\n\n## Docs ###\n\n- [Home](https://example.com)\n";

        $llmsTxt = (new LlmsTxtParser())->parse($content);

        self::assertSame('Title', $llmsTxt->getTitle());
        self::assertSame('Docs', $llmsTxt->getSections()[0]->getName());
    }

    #[Test]
    public function itKeepsNumberSignsThatAreNoClosingSequence(): void
    {
        $content = "# Title###\n\n## C# Docs\n\n- [Home](https://example.com)\n";

        $llmsTxt = (new LlmsTxtParser())->parse($content);

        self::assertSame('Title###', $llmsTxt->getTitle());
        self::assertSame('C# Docs', $llmsTxt->getSections()[0]->getName());
    }

    #[Test]
    public function itParsesTheBulletMarkersOfMarkdown(): void
    {
        $content = <<<MD
            # Title

            ## Docs

            * [Asterisk](https://example.com/1)
            + [Plus](https://example.com/2)
            - [Dash](https://example.com/3)
            MD;

        $llmsTxt = (new LlmsTxtParser())->parse($content);

        $links = $llmsTxt->getSections()[0]->getLinks();

        self::assertCount(3, $links);
        self::assertSame('Asterisk', $links[0]->getUrlTitle());
        self::assertSame('Plus', $links[1]->getUrlTitle());
        self::assertSame('Dash', $links[2]->getUrlTitle());
    }

    #[Test]
    public function itLeavesTheLinkTitleOutOfTheUrl(): void
    {
        $content = <<<MD
            # Title

            ## Docs

            - [Double](https://example.com/1 "A link title"): Details
            - [Single](https://example.com/2 'A link title')
            - [Parenthesised](https://example.com/3 (A link title))
            MD;

        $llmsTxt = (new LlmsTxtParser())->parse($content);

        $links = $llmsTxt->getSections()[0]->getLinks();

        self::assertSame('https://example.com/1', $links[0]->getUrl());
        self::assertSame('Details', $links[0]->getUrlDetails());
        self::assertSame('https://example.com/2', $links[1]->getUrl());
        self::assertSame('https://example.com/3', $links[2]->getUrl());
    }

    #[Test]
    public function itStripsTheAngleBracketsOfALinkDestination(): void
    {
        $content = <<<MD
            # Title

            ## Docs

            - [Wrapped](<https://example.com/a>)
            - [Spaced](<https://example.com/a b>): Details
            MD;

        $llmsTxt = (new LlmsTxtParser())->parse($content);

        $links = $llmsTxt->getSections()[0]->getLinks();

        self::assertSame('https://example.com/a', $links[0]->getUrl());
        self::assertSame('https://example.com/a b', $links[1]->getUrl());
        self::assertSame('Details', $links[1]->getUrlDetails());
    }

    #[Test]
    public function itDoesNotTreatDeeperHeadingsAsSections(): void
    {
        $content = "# Title\n\n## Docs\n\n### Subsection\n\n- [Guide](https://example.com)\n";

        $llmsTxt = (new LlmsTxtParser())->parse($content);

        self::assertCount(1, $llmsTxt->getSections());
        self::assertSame('Docs', $llmsTxt->getSections()[0]->getName());
        self::assertCount(1, $llmsTxt->getSections()[0]->getLinks());
    }

    #[Test]
    public function itParsesAContentLessFile(): void
    {
        $llmsTxt = (new LlmsTxtParser())->parse('');

        self::assertSame('', $llmsTxt->getTitle());
        self::assertSame('', $llmsTxt->getDescription());
        self::assertSame('', $llmsTxt->getDetails());
        self::assertSame([], $llmsTxt->getSections());
    }

    #[Test]
    public function itParsesATitleLessFile(): void
    {
        $content = "> Description\n\n## Docs\n\n- [Guide](https://example.com)\n";

        $llmsTxt = (new LlmsTxtParser())->parse($content);

        self::assertSame('', $llmsTxt->getTitle());
        self::assertSame('Description', $llmsTxt->getDescription());
        self::assertCount(1, $llmsTxt->getSections());
    }

    #[Test]
    public function itParsesABlockquoteWithoutASpace(): void
    {
        $llmsTxt = (new LlmsTxtParser())->parse("# Title\n\n>Description\n");

        self::assertSame('Description', $llmsTxt->getDescription());
    }

    #[Test]
    public function itAllowsValidatingAParsedLlmsTxt(): void
    {
        $llmsTxt = (new LlmsTxtParser())->parse("# Title\n");

        self::assertTrue($llmsTxt->validate());
    }

    #[Test]
    public function itParsesAFile(): void
    {
        $llmsTxt = (new LlmsTxtParser())->parseFile(
            __DIR__ . '/../fixtures/example.md'
        );

        self::assertSame('Title', $llmsTxt->getTitle());
        self::assertSame('Optional description goes here', $llmsTxt->getDescription());
        self::assertSame('Optional details go here', $llmsTxt->getDetails());
        self::assertCount(2, $llmsTxt->getSections());
    }

    #[Test]
    public function itThrowsOnANonExistentFile(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not exist');

        (new LlmsTxtParser())->parseFile(__DIR__ . '/../fixtures/nope.md');
    }

    #[Test]
    public function parseRenderRoundTripPreservesContent(): void
    {
        $content = <<<MD
            # Project

            > A project description.

            First paragraph.

            Second paragraph with **Markdown**.

            A paragraph spanning
            two lines.

            ## Documentation

            - [Installation](https://example.com/install): Installation instructions.
            - [Guide](https://example.com/foo(bar)): A URL with parentheses.

            ## API

            - [Reference](https://example.com/api): API reference.
            MD;

        $llmsTxt = (new LlmsTxt())->parse($content);

        // `toString` terminates the rendered file with a newline.
        self::assertSame($content . "\n", $llmsTxt->toString());
    }
}
