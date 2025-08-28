<?php

declare(strict_types=1);

namespace Stolt\LlmsTxt;

use DOMDocument;
use DOMXPath;

final class Extractor
{
    /**
     * Extracts the contents of all <script type="text/llms.txt"> blocks from raw HTML.
     *
     * @param string $html Raw HTML string.
     * @return string[] Extracted contents.
     */
    public function extractFromHtml(string $html): array
    {
        $dom = new DOMDocument();
        // Suppress warnings from malformed HTML
        @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//script[@type="text/llms.txt"]');

        $contents = [];
        foreach ($nodes as $node) {
            $contents[] = \trim($node->textContent);
        }

        return $contents;
    }
}
