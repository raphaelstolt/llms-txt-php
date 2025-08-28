<?php

declare(strict_types=1);

namespace Stolt\LlmsTxt\Tests;

use PHPUnit\Framework\Attributes\Test;
use Stolt\LlmsTxt\Extractor;

final class ExtractorTest extends TestCase
{
    private Extractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new Extractor();
    }

    #[Test]
    public function extractsSingleBlockFromHtml(): void
    {
        $html = '<html><body><script type="text/llms.txt">content-one</script></body></html>';

        $this->assertSame(['content-one'], $this->extractor->extractFromHtml($html));
    }

    #[Test]
    public function extractsMultipleBlocksFromHtml(): void
    {
        $html = <<<HTML
        <html>
          <body>
            <script type="text/llms.txt">first-block</script>
            Some other content.
            <p>And some more content.</p>
            <br />
            <script type="text/llms.txt">second-block</script>
          </body>
        </html>
        HTML;

        $this->assertSame(['first-block', 'second-block'], $this->extractor->extractFromHtml($html));
    }

    #[Test]
    public function returnsAnEmptyArrayWhenNoBlocksAreFound(): void
    {
        $html = '<html><body><p>No llms.txt here</p></body></html>';

        $this->assertSame([], $this->extractor->extractFromHtml($html));
    }
}
