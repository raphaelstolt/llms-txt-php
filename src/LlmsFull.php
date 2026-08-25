<?php

declare(strict_types=1);

namespace Stolt\LlmsTxt;

final class LlmsFull
{
    /**
     * Expands the linked documents of a given llms.txt file into a llms-full.txt Markdown file.
     *
     * The header of the given llms.txt file is kept as it is, every file list entry becomes a
     * `### Link title` block holding its details, its source URL, and the fetched document. The
     * fetched documents are inlined as they are, since a llms-full.txt file is meant to carry the
     * full text of the documents it covers.
     *
     * The details of a file list entry are rendered as prose instead of a quoted line, so that the
     * description of the llms.txt header stays the only `> ` line of the expanded file.
     *
     * Since v2 of the llms.txt specification the `Optional` section carries no mechanical
     * semantics, therefore all sections are expanded unless `$skipOptional` is set.
     *
     * @param callable(string): string|null $fetcher
     */
    public function expand(LlmsTxt $llmsTxt, bool $skipOptional = false, ?callable $fetcher = null): string
    {
        $fetcher ??= [new LlmContext(), 'fetch'];

        $llmsFull = '# ' . $llmsTxt->getTitle() . PHP_EOL . PHP_EOL;

        if ($llmsTxt->getDescription() !== '') {
            $llmsFull .= '> ' . $llmsTxt->getDescription() . PHP_EOL . PHP_EOL;
        }

        if ($llmsTxt->getDetails() !== '') {
            $llmsFull .= $llmsTxt->getDetails() . PHP_EOL . PHP_EOL;
        }

        foreach ($llmsTxt->getSections() as $section) {
            if ($skipOptional && $section->getName() === LlmsTxt::OPTIONAL_SECTION_NAME) {
                continue;
            }

            $llmsFull .= '## ' . $section->getName() . PHP_EOL . PHP_EOL;

            foreach ($section->getLinks() as $link) {
                $llmsFull .= $this->expandLink($link, $fetcher);
            }
        }

        return \rtrim($llmsFull, PHP_EOL) . PHP_EOL;
    }

    /**
     * @param callable(string): string $fetcher
     */
    private function expandLink(Section\Link $link, callable $fetcher): string
    {
        $expandedLink = '### ' . $link->getUrlTitle() . PHP_EOL . PHP_EOL;

        if ($link->getUrlDetails() !== '') {
            $expandedLink .= $link->getUrlDetails() . PHP_EOL . PHP_EOL;
        }

        $expandedLink .= 'Source: ' . $link->getUrl() . PHP_EOL . PHP_EOL;

        $body = \trim($fetcher($link->getUrl()));

        if ($body !== '') {
            $expandedLink .= $body . PHP_EOL . PHP_EOL;
        }

        return $expandedLink;
    }
}
