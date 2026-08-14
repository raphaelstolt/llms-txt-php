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
    public function itSkipsTheOptionalSectionByDefault(): void
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
</project>

XML;

        $this->assertSame($expected, $context);
        $this->assertStringNotContainsString('<optional>', $context);
    }

    #[Test]
    public function itIncludesTheOptionalSectionWhenRequested(): void
    {
        $context = $this->parsedExample()->toLlmContext(
            true,
            $this->stubFetcher(['https://link_url' => 'Fetched body'])
        );

        $this->assertStringContainsString('<optional>', $context);
        $this->assertStringContainsString(
            '<doc title="Link title">Fetched body</doc>',
            $context
        );
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
