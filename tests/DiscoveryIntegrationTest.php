<?php

declare(strict_types=1);

namespace Stolt\LlmsTxt\Tests;

use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Stolt\LlmsTxt\Discovery;
use Stolt\LlmsTxt\LlmContext;
use Stolt\LlmsTxt\LlmsTxt;
use Stolt\LlmsTxt\Section;
use Stolt\LlmsTxt\Section\Link;

/**
 * Covers the interplay of Discovery and LlmsTxt, discovery locates a llms.txt file
 * or a markdown page version, LlmsTxt reads and expands what has been located.
 */
final class DiscoveryIntegrationTest extends TestCase
{
    /**
     * The llms.txt files of a documentation tree, keyed by their URL.
     *
     * @return array<string, LlmsTxt>
     */
    private function documentationTree(): array
    {
        return [
            '/llms.txt' => (new LlmsTxt())->parse(<<<LLMS_TXT_MD
            # Acme

            > Everything about Acme.

            ## Docs

            - [Getting started](/docs/getting-started.html)
            LLMS_TXT_MD),
            '/docs/api/llms.txt' => (new LlmsTxt())->parse(<<<LLMS_TXT_MD
            # Acme API

            > The HTTP API of Acme.

            ## Endpoints

            - [Endpoints](/docs/api/v2/endpoints.html)
            LLMS_TXT_MD),
        ];
    }

    #[Test]
    public function itParsesTheLlmsTxtALinkElementDescribesAPageBy(): void
    {
        $this->setUpTemporaryDirectory();
        $llmsTxtPath = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'llms.txt';
        $discovery = new Discovery();

        (new LlmsTxt())->title('Acme')
            ->description('Everything about Acme.')
            ->details('Acme builds things.')
            ->addSection(
                (new Section())->name('Docs')->addLink(
                    (new Link())->urlTitle('Getting started')->url('/docs/getting-started.html')
                )
            )
            ->toFile($llmsTxtPath);

        $html = '<html><head>'
            . $discovery->asLinkElements($llmsTxtPath, '/docs/getting-started.html.md')
            . '</head><body>Getting started</body></html>';

        $describedByUrls = $discovery->describedByUrls($html);

        $this->assertSame([$llmsTxtPath], $describedByUrls);

        $llmsTxt = (new LlmsTxt())->parse($describedByUrls[0]);

        $this->assertTrue($llmsTxt->validate());
        $this->assertSame('Acme', $llmsTxt->getTitle());
        $this->assertSame(
            '/docs/getting-started.html',
            $llmsTxt->getSectionByName('Docs')->getLinks()[0]->getUrl()
        );
    }

    #[Test]
    public function itParsesTheLlmsTxtALinkHeaderDescribesAPageBy(): void
    {
        $this->setUpTemporaryDirectory();
        $llmsTxtPath = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'llms.txt';
        $discovery = new Discovery();

        (new LlmsTxt())->title('Acme')->toFile($llmsTxtPath);

        $linkHeader = $discovery->asLinkHeader($llmsTxtPath, '/docs/getting-started.html.md');

        $describedByUrls = $discovery->describedByUrlsFromHeader($linkHeader);

        $this->assertSame([$llmsTxtPath], $describedByUrls);
        $this->assertSame(
            ['/docs/getting-started.html.md'],
            $discovery->markdownAlternatesFromHeader($linkHeader)
        );
        $this->assertSame('Acme', (new LlmsTxt())->parse($describedByUrls[0])->getTitle());
    }

    #[Test]
    public function itReadsTheMostSpecificLlmsTxtCoveringAPage(): void
    {
        $discovery = new Discovery();
        $llmsTxts = $this->documentationTree();

        $coveringUrl = $discovery->coveringUrl('/docs/api/v2/endpoints.html', \array_keys($llmsTxts));

        $this->assertSame('/docs/api/llms.txt', $coveringUrl);
        $this->assertSame('Acme API', $llmsTxts[$coveringUrl]->getTitle());
        $this->assertSame(
            '/llms.txt',
            $discovery->coveringUrl('/about.html', \array_keys($llmsTxts))
        );
    }

    #[Test]
    public function itAnnouncesTheLlmsTxtCoveringAPageAlongsideItsMarkdownVersion(): void
    {
        $page = '/docs/api/v2/endpoints.html';
        $discovery = new Discovery();
        $llmsTxts = $this->documentationTree();

        $linkHeader = $discovery->asLinkHeader(
            $discovery->coveringUrl($page, \array_keys($llmsTxts)) ?? '',
            $discovery->markdownUrls($page)[0]
        );

        $this->assertSame(
            '</docs/api/v2/endpoints.html.md>; rel="alternate"; type="text/markdown", '
            . '</docs/api/llms.txt>; rel="describedby"',
            $linkHeader
        );
    }

    #[Test]
    public function itExpandsALlmsTxtThroughTheMarkdownVersionsOfItsLinks(): void
    {
        $this->setUpTemporaryDirectory();
        $page = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'guide.html';
        $markdownPage = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'guide.md';

        \file_put_contents($page, '<html><body>HTML body</body></html>');
        \file_put_contents($markdownPage, 'Markdown body');

        $llmsTxt = (new LlmsTxt())->title('Acme')
            ->addSection(
                (new Section())->name('Docs')->addLink(
                    (new Link())->urlTitle('Guide')->url($page)
                )
            );

        $context = $llmsTxt->toLlmContext(false, $this->markdownPreferringFetcher());

        $expected = <<<XML
        <project title="Acme">
        <docs>
        <doc title="Guide">Markdown body</doc>
        </docs>
        </project>

        XML;

        $this->assertSame($expected, $context);
    }

    #[Test]
    public function itFallsBackToThePageWhenItHasNoMarkdownVersion(): void
    {
        $this->setUpTemporaryDirectory();
        $page = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'guide.html';

        \file_put_contents($page, 'HTML body');

        $llmsTxt = (new LlmsTxt())->title('Acme')
            ->addSection(
                (new Section())->name('Docs')->addLink(
                    (new Link())->urlTitle('Guide')->url($page)
                )
            );

        $context = $llmsTxt->toLlmContext(false, $this->markdownPreferringFetcher());

        $this->assertStringContainsString('<doc title="Guide">HTML body</doc>', $context);
    }

    /**
     * Resolves a linked page through its markdown versions, and through the page itself
     * when none of them is available.
     */
    private function markdownPreferringFetcher(): callable
    {
        $discovery = new Discovery();
        $llmContext = new LlmContext();

        return static function (string $url) use ($discovery, $llmContext): string {
            foreach ($discovery->markdownUrls($url) as $markdownUrl) {
                try {
                    return $llmContext->fetch($markdownUrl);
                } catch (RuntimeException) {
                    continue;
                }
            }

            return $llmContext->fetch($url);
        };
    }
}
