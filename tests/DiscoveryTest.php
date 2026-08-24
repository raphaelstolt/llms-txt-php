<?php

declare(strict_types=1);

namespace Stolt\LlmsTxt\Tests;

use PHPUnit\Framework\Attributes\Test;
use Stolt\LlmsTxt\Discovery;

final class DiscoveryTest extends TestCase
{
    private Discovery $discovery;

    protected function setUp(): void
    {
        $this->discovery = new Discovery();
    }

    #[Test]
    public function rendersBothLinkElements(): void
    {
        $expected = <<<HTML
        <link rel="alternate" type="text/markdown" href="/docs/page.html.md">
        <link rel="describedby" href="/docs/llms.txt">

        HTML;

        $this->assertSame(
            $expected,
            $this->discovery->asLinkElements('/docs/llms.txt', '/docs/page.html.md')
        );
    }

    #[Test]
    public function rendersOnlyTheGivenLinkElement(): void
    {
        $this->assertSame(
            '<link rel="describedby" href="/llms.txt">' . PHP_EOL,
            $this->discovery->asLinkElements('/llms.txt')
        );
        $this->assertSame(
            '<link rel="alternate" type="text/markdown" href="/page.md">' . PHP_EOL,
            $this->discovery->asLinkElements('', '/page.md')
        );
    }

    #[Test]
    public function rendersNoLinkElementsWithoutUrls(): void
    {
        $this->assertSame('', $this->discovery->asLinkElements());
    }

    #[Test]
    public function escapesLinkElementUrls(): void
    {
        $elements = $this->discovery->asLinkElements('/llms.txt?a="b"&c=d');

        $this->assertStringContainsString('href="/llms.txt?a=&quot;b&quot;&amp;c=d"', $elements);
    }

    #[Test]
    public function rendersTheLinkHeaderValue(): void
    {
        $this->assertSame(
            '</docs/page.html.md>; rel="alternate"; type="text/markdown", </docs/llms.txt>; rel="describedby"',
            $this->discovery->asLinkHeader('/docs/llms.txt', '/docs/page.html.md')
        );
    }

    #[Test]
    public function rendersOnlyTheGivenLinkHeaderValue(): void
    {
        $this->assertSame(
            '</docs/llms.txt>; rel="describedby"',
            $this->discovery->asLinkHeader('/docs/llms.txt')
        );
        $this->assertSame('', $this->discovery->asLinkHeader());
    }

    #[Test]
    public function readsLinkRelationsFromHtml(): void
    {
        $html = <<<HTML
        <html>
          <head>
            <link rel="alternate" type="text/markdown" href="/docs/page.html.md">
            <link rel="describedby" href="/docs/llms.txt">
            <link rel="stylesheet" href="/style.css">
          </head>
          <body>Content</body>
        </html>
        HTML;

        $this->assertSame(['/docs/llms.txt'], $this->discovery->describedByUrls($html));
        $this->assertSame(['/docs/page.html.md'], $this->discovery->markdownAlternates($html));
    }

    #[Test]
    public function readsLinkRelationsFromMultiTokenRelationsInHtml(): void
    {
        $html = '<html><head><link rel="Alternate DescribedBy" type="text/markdown" href="/llms.txt">'
            . '</head></html>';

        $this->assertSame(['/llms.txt'], $this->discovery->describedByUrls($html));
        $this->assertSame(['/llms.txt'], $this->discovery->markdownAlternates($html));
    }

    #[Test]
    public function ignoresAlternatesWhichAreNoMarkdownInHtml(): void
    {
        $html = '<html><head><link rel="alternate" type="application/rss+xml" href="/feed.xml">'
            . '<link rel="alternate" href="/page.md"></head></html>';

        $this->assertSame([], $this->discovery->markdownAlternates($html));
    }

    #[Test]
    public function readsNoLinkRelationsFromHtmlWithoutAny(): void
    {
        $html = '<html><body><p>No link relations here</p></body></html>';

        $this->assertSame([], $this->discovery->describedByUrls($html));
        $this->assertSame([], $this->discovery->markdownAlternates($html));
    }

    #[Test]
    public function readsLinkRelationsFromALinkHeader(): void
    {
        $header = '</docs/page.html.md>; rel="alternate"; type="text/markdown", '
            . '</docs/llms.txt>; rel="describedby"';

        $this->assertSame(['/docs/llms.txt'], $this->discovery->describedByUrlsFromHeader($header));
        $this->assertSame(['/docs/page.html.md'], $this->discovery->markdownAlternatesFromHeader($header));
    }

    #[Test]
    public function readsLinkRelationsFromAnUnquotedLinkHeader(): void
    {
        $header = '</docs/page.md>; rel=alternate; type=text/markdown, </llms.txt>; rel=describedby';

        $this->assertSame(['/llms.txt'], $this->discovery->describedByUrlsFromHeader($header));
        $this->assertSame(['/docs/page.md'], $this->discovery->markdownAlternatesFromHeader($header));
    }

    #[Test]
    public function readsNoLinkRelationsFromAnEmptyLinkHeader(): void
    {
        $this->assertSame([], $this->discovery->describedByUrlsFromHeader(''));
        $this->assertSame([], $this->discovery->markdownAlternatesFromHeader('no links in here'));
    }

    #[Test]
    public function roundTripsTheRenderedLinkElements(): void
    {
        $elements = $this->discovery->asLinkElements('/docs/llms.txt', '/docs/page.html.md');

        $this->assertSame(['/docs/llms.txt'], $this->discovery->describedByUrls($elements));
        $this->assertSame(['/docs/page.html.md'], $this->discovery->markdownAlternates($elements));
    }

    #[Test]
    public function roundTripsTheRenderedLinkHeader(): void
    {
        $header = $this->discovery->asLinkHeader('/docs/llms.txt', '/docs/page.html.md');

        $this->assertSame(['/docs/llms.txt'], $this->discovery->describedByUrlsFromHeader($header));
        $this->assertSame(['/docs/page.html.md'], $this->discovery->markdownAlternatesFromHeader($header));
    }

    #[Test]
    public function returnsBothMarkdownUrlFormsForAFileNameWithAnExtension(): void
    {
        $this->assertSame(
            ['/docs/page.html.md', '/docs/page.md'],
            $this->discovery->markdownUrls('/docs/page.html')
        );
    }

    #[Test]
    public function returnsTheIndexMarkdownUrlFormsForAUrlWithoutAFileName(): void
    {
        $this->assertSame(
            ['/docs/index.html.md', '/docs/index.md'],
            $this->discovery->markdownUrls('/docs/')
        );
        $this->assertSame(['/index.html.md', '/index.md'], $this->discovery->markdownUrls('/'));
        $this->assertSame(
            ['https://example.test/index.html.md', 'https://example.test/index.md'],
            $this->discovery->markdownUrls('https://example.test')
        );
    }

    #[Test]
    public function returnsOneMarkdownUrlFormForAFileNameWithoutAnExtension(): void
    {
        $this->assertSame(['/docs/page.md'], $this->discovery->markdownUrls('/docs/page'));
    }

    #[Test]
    public function keepsTheQueryAndFragmentOfAMarkdownUrl(): void
    {
        $this->assertSame(
            [
                'https://example.test/docs/page.html.md?edit=1#top',
                'https://example.test/docs/page.md?edit=1#top',
            ],
            $this->discovery->markdownUrls('https://example.test/docs/page.html?edit=1#top')
        );
    }

    #[Test]
    public function returnsTheCoveringUrlsMostSpecificFirst(): void
    {
        $this->assertSame(
            ['/docs/a/llms.txt', '/docs/llms.txt', '/llms.txt'],
            $this->discovery->coveringUrls('/docs/a/b.html')
        );
        $this->assertSame(['/llms.txt'], $this->discovery->coveringUrls('/page.html'));
        $this->assertSame(
            ['https://example.test/docs/llms.txt', 'https://example.test/llms.txt'],
            $this->discovery->coveringUrls('https://example.test/docs/page.html')
        );
    }

    #[Test]
    public function returnsTheMostSpecificCoveringUrl(): void
    {
        $candidates = ['/llms.txt', '/docs/llms.txt'];

        $this->assertSame('/docs/llms.txt', $this->discovery->coveringUrl('/docs/a/b.html', $candidates));
        $this->assertSame('/llms.txt', $this->discovery->coveringUrl('/about.html', $candidates));
    }

    #[Test]
    public function returnsNoCoveringUrlWhenNoneCovers(): void
    {
        $this->assertNull($this->discovery->coveringUrl('/docs/a.html', ['/blog/llms.txt']));
        $this->assertNull($this->discovery->coveringUrl('/docs/a.html', []));
    }
}
