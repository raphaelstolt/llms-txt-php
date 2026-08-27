<?php

declare(strict_types=1);

namespace Stolt\LlmsTxt\Tests;

use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Stolt\LlmsTxt\LlmsTxt;
use Stolt\LlmsTxt\Section;
use Stolt\LlmsTxt\Section\Link;

final class LlmContextTest extends TestCase
{
    private function stubFetcher(array $bodies): callable
    {
        return function (string $url) use ($bodies): string {
            if (!\array_key_exists($url, $bodies)) {
                throw new RuntimeException("Unable to fetch LLM context from {$url}");
            }

            return $bodies[$url];
        };
    }

    private function parsedExample(): LlmsTxt
    {
        return (new LlmsTxt())->parse(\realpath(__DIR__ . '/fixtures/example.md'));
    }

    #[Test]
    public function itIncludesTheOptionalSectionByDefault(): void
    {
        $context = $this->parsedExample()->toLlmContext(
            false,
            $this->stubFetcher(['https://link_url' => 'Section body'])
        );

        $expected = <<<XML
<project title="Title" summary="Optional description goes here">
Optional details go here
<section-name>
<doc title="Link title" desc="Optional link details">Section body</doc>
</section-name>
<optional>
<doc title="Link title">Section body</doc>
</optional>
</project>

XML;

        $this->assertSame($expected, $context);
    }

    #[Test]
    public function itSkipsTheOptionalSectionWhenRequested(): void
    {
        $context = $this->parsedExample()->toLlmContext(
            true,
            $this->stubFetcher(['https://link_url' => 'Fetched body'])
        );

        $this->assertStringNotContainsString('<optional>', $context);
        $this->assertStringContainsString(
            '<doc title="Link title" desc="Optional link details">Fetched body</doc>',
            $context
        );
    }

    #[Test]
    public function itExpandsAnOptionalSectionSetViaTheSetter(): void
    {
        $llmsTxt = (new LlmsTxt())->title('Title')
            ->addSection(
                (new Section())->name('Docs')->addLink(
                    (new Link())->urlTitle('Doc')->url('https://example.test/doc.md')
                )
            )
            ->optional(
                (new Section())->addLink(
                    (new Link())->urlTitle('Secondary doc')->url('https://example.test/secondary.md')
                )
            );
        $fetcher = $this->stubFetcher([
            'https://example.test/doc.md' => 'Doc body',
            'https://example.test/secondary.md' => 'Secondary body',
        ]);

        $this->assertStringContainsString('Secondary body', $llmsTxt->toLlmContext(false, $fetcher));
        $this->assertStringNotContainsString('Secondary body', $llmsTxt->toLlmContext(true, $fetcher));
    }

    #[Test]
    public function itWritesTheContextToAFile(): void
    {
        $this->setUpTemporaryDirectory();
        $path = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'llms-ctx.txt';

        $llmsTxt = $this->parsedExample();
        $fetcher = $this->stubFetcher(['https://link_url' => 'Section body']);

        $this->assertTrue($llmsTxt->toLlmContextFile($path, false, $fetcher));
        $this->assertSame($llmsTxt->toLlmContext(false, $fetcher), \file_get_contents($path));
    }

    #[Test]
    public function itThrowsWhenTheFetcherFails(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to fetch LLM context from https://link_url');

        $this->parsedExample()->toLlmContext(false, function (string $url): string {
            throw new RuntimeException("Unable to fetch LLM context from {$url}");
        });
    }

    #[Test]
    public function itReadsALocalFilePathWithoutACustomFetcher(): void
    {
        $this->setUpTemporaryDirectory();
        $bodyPath = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'doc.md';
        \file_put_contents($bodyPath, 'Local file body');

        $llmsTxt = (new LlmsTxt())->title('Local')
            ->addSection(
                (new Section())->name('Docs')->addLink(
                    (new Link())->urlTitle('Local doc')->url($bodyPath)
                )
            );

        $expected = <<<XML
<project title="Local">
<docs>
<doc title="Local doc">Local file body</doc>
</docs>
</project>

XML;

        $this->assertSame($expected, $llmsTxt->toLlmContext());
    }

    #[Test]
    public function itEscapesQuotesInProjectAttributes(): void
    {
        $this->setUpTemporaryDirectory();
        $bodyPath = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'doc.md';
        \file_put_contents($bodyPath, 'Body');

        $llmsTxt = (new LlmsTxt())->title('Tom\'s "Docs"')
            ->description('It\'s "great"')
            ->addSection(
                (new Section())->name('Docs')->addLink(
                    (new Link())->urlTitle('A "quoted" title')->url($bodyPath)
                )
            );

        $context = $llmsTxt->toLlmContext();

        $this->assertStringContainsString(
            'title="Tom&apos;s &quot;Docs&quot;"',
            $context
        );
        $this->assertStringContainsString(
            'summary="It&apos;s &quot;great&quot;"',
            $context
        );
        $this->assertStringContainsString(
            'title="A &quot;quoted&quot; title"',
            $context
        );
    }

    #[Test]
    public function itStripsBlankSpaceLinesAndBase64ImagesFromFetchedBodies(): void
    {
        $raw = "Keep me\n \n<img src=\"data:image/png;base64,abc\" />\nAlso keep";

        $llmsTxt = (new LlmsTxt())->title('Clean')
            ->addSection(
                (new Section())->name('Docs')->addLink(
                    (new Link())->urlTitle('Doc')->url('https://example.test/doc.md')
                )
            );

        $context = $llmsTxt->toLlmContext(
            false,
            $this->stubFetcher(['https://example.test/doc.md' => $raw])
        );

        $this->assertStringContainsString(">Keep me\nAlso keep</doc>", $context);
        $this->assertStringNotContainsString('data:image', $context);
    }

    #[Test]
    public function itEscapesSpecialCharactersInDetails(): void
    {
        $llmsTxt = (new LlmsTxt())->title('Acme Docs')
            ->description('Guides for R&D and Q&A')
            ->details('Covers R&D, a < b comparisons & more.')
            ->addSection(
                (new Section())->name('Docs')->addLink(
                    (new Link())->urlTitle('Doc')->url('https://example.test/doc.md')
                )
            );

        $context = $llmsTxt->toLlmContext(
            false,
            $this->stubFetcher(['https://example.test/doc.md' => 'Body'])
        );

        $this->assertStringContainsString(
            'Covers R&amp;D, a &lt; b comparisons &amp; more.',
            $context
        );
        $this->assertWellFormedXml($context);
    }

    #[Test]
    public function itEscapesSpecialCharactersInFetchedDocBodies(): void
    {
        $llmsTxt = (new LlmsTxt())->title('Acme Docs')
            ->addSection(
                (new Section())->name('Docs')->addLink(
                    (new Link())->urlTitle('Doc')->url('https://example.test/doc.md')
                )
            );

        $context = $llmsTxt->toLlmContext(
            false,
            $this->stubFetcher([
                'https://example.test/doc.md' => 'Use `a < b` in guards & see R&D notes.',
            ])
        );

        $this->assertStringContainsString(
            '>Use `a &lt; b` in guards &amp; see R&amp;D notes.</doc>',
            $context
        );
        $this->assertWellFormedXml($context);
    }

    #[Test]
    public function itKeepsQuotesOfTheCharacterDataAsTheyAre(): void
    {
        $llmsTxt = (new LlmsTxt())->title('Acme Docs')
            ->details('The "details" of it\'s expansion.')
            ->addSection(
                (new Section())->name('Docs')->addLink(
                    (new Link())->urlTitle('Doc')->url('https://example.test/doc.md')
                )
            );

        $context = $llmsTxt->toLlmContext(
            false,
            $this->stubFetcher(['https://example.test/doc.md' => 'A "quoted" body.'])
        );

        $this->assertStringContainsString('The "details" of it\'s expansion.', $context);
        $this->assertStringContainsString('>A "quoted" body.</doc>', $context);
        $this->assertWellFormedXml($context);
    }

    #[Test]
    public function itExpandsAParsedLlmsTxtFileIntoWellFormedXml(): void
    {
        $context = $this->parsedExample()->toLlmContext(
            false,
            $this->stubFetcher(['https://link_url' => 'Use `a < b` in guards & see R&D notes.'])
        );

        $this->assertWellFormedXml($context);
    }

    private function assertWellFormedXml(string $xml): void
    {
        $previous = \libxml_use_internal_errors(true);
        \libxml_clear_errors();

        $loaded = (new \DOMDocument())->loadXML($xml);
        $errors = \libxml_get_errors();

        \libxml_clear_errors();
        \libxml_use_internal_errors($previous);

        $this->assertTrue(
            $loaded,
            'Expected well-formed XML, got: ' . \implode(
                ', ',
                \array_map(static fn (\LibXMLError $error): string => \trim($error->message), $errors)
            )
        );
    }

    #[Test]
    public function theDefaultFetcherThrowsForAMissingPath(): void
    {
        $missing = '/this/path/does/not/exist.md';

        $llmsTxt = (new LlmsTxt())->title('Missing')
            ->addSection(
                (new Section())->name('Docs')->addLink(
                    (new Link())->urlTitle('Gone')->url($missing)
                )
            );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Unable to fetch LLM context from {$missing}");

        $llmsTxt->toLlmContext();
    }
}
