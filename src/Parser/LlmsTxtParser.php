<?php

declare(strict_types=1);

namespace Stolt\LlmsTxt\Parser;

use RuntimeException;
use Stolt\LlmsTxt\LlmsTxt;
use Stolt\LlmsTxt\Section;
use Stolt\LlmsTxt\Section\Link;

final class LlmsTxtParser
{
    public const BYTE_ORDER_MARK = "\xEF\xBB\xBF";

    /**
     * Parse llms.txt content into an LlmsTxt document.
     */
    public function parse(string $content): LlmsTxt
    {
        $lines = \explode("\n", $this->normalize($content));
        $lineCount = \count($lines);

        $llmsTxt = new LlmsTxt();

        $index = $this->skipBlankLines($lines, 0);

        // H1 title.
        if ($index < $lineCount && \preg_match('/^# (.+)$/', $lines[$index], $matches) === 1) {
            $llmsTxt->title(\trim($matches[1]));
            $index++;
        }

        $index = $this->skipBlankLines($lines, $index);

        // Optional blockquote summary.
        if ($index < $lineCount && \preg_match('/^> ?(.*)$/', $lines[$index], $matches) === 1) {
            $llmsTxt->description(\trim($matches[1]));
            $index++;
        }

        // Everything up to the first H2 is the details section.
        $details = [];

        while ($index < $lineCount && !$this->isSectionHeading($lines[$index])) {
            $details[] = $lines[$index];
            $index++;
        }

        $llmsTxt->details($this->trimBlankLines($details));

        // H2 sections and their file lists.
        while ($index < $lineCount) {
            if (!$this->isSectionHeading($lines[$index])) {
                $index++;

                continue;
            }

            $section = $this->sectionFor($llmsTxt, $this->getSectionName($lines[$index]));
            $index++;

            while ($index < $lineCount && !$this->isSectionHeading($lines[$index])) {
                $link = $this->parseLink($lines[$index]);

                if ($link !== null) {
                    $section->addLink($link);
                }

                $index++;
            }
        }

        return $llmsTxt->markAsParsed();
    }

    /**
     * Parse a llms.txt file.
     *
     * @throws RuntimeException
     */
    public function parseFile(string $path): LlmsTxt
    {
        if (!\is_file($path)) {
            throw new RuntimeException(
                \sprintf('Unable to parse llms.txt: file "%s" does not exist.', $path)
            );
        }

        $content = @\file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException(
                \sprintf('Unable to read llms.txt file "%s".', $path)
            );
        }

        return $this->parse($content);
    }

    /**
     * Normalize only transport-level differences.
     *
     * Markdown-significant whitespace must not be changed here.
     */
    private function normalize(string $content): string
    {
        if (\str_starts_with($content, self::BYTE_ORDER_MARK)) {
            $content = \substr($content, \strlen(self::BYTE_ORDER_MARK));
        }

        return \str_replace(["\r\n", "\r"], "\n", $content);
    }

    /**
     * @param list<string> $lines
     */
    private function skipBlankLines(array $lines, int $index): int
    {
        $count = \count($lines);

        while ($index < $count && \trim($lines[$index]) === '') {
            $index++;
        }

        return $index;
    }

    private function isSectionHeading(string $line): bool
    {
        return \preg_match('/^## (.+)$/', $line) === 1;
    }

    private function getSectionName(string $line): string
    {
        return \trim(\substr($line, 3));
    }

    /**
     * Returns the section of the given name, a not yet known one gets appended.
     *
     * Sections of the same name are merged, their file lists end up in the
     * section the first heading of that name created.
     */
    private function sectionFor(LlmsTxt $llmsTxt, string $name): Section
    {
        $section = $llmsTxt->getSectionByName($name);

        if ($section === null) {
            $section = (new Section())->name($name);
            $llmsTxt->addSection($section);
        }

        return $section;
    }

    /**
     * @param list<string> $lines
     */
    private function trimBlankLines(array $lines): string
    {
        while ($lines !== [] && \trim($lines[0]) === '') {
            \array_shift($lines);
        }

        while ($lines !== [] && \trim($lines[\array_key_last($lines)]) === '') {
            \array_pop($lines);
        }

        return \implode("\n", $lines);
    }

    /**
     * Parses a file list line into a link, null when the line holds no Markdown link.
     *
     * A file list entry of the specification is a `- [title](url)` line optionally
     * followed by `: details`, anything else is no entry and gets skipped instead
     * of turned into an empty link.
     */
    private function parseLink(string $line): ?Link
    {
        $line = \trim($line);

        if (!\str_starts_with($line, '- [')) {
            return null;
        }

        $titleEnd = \strpos($line, '](');

        if ($titleEnd === false) {
            return null;
        }

        $title = \substr($line, 3, $titleEnd - 3);
        $urlStart = $titleEnd + 2;
        $urlEnd = $this->findClosingParenthesis($line, $urlStart);

        if ($urlEnd === null) {
            return null;
        }

        $url = \substr($line, $urlStart, $urlEnd - $urlStart);
        $remaining = \trim(\substr($line, $urlEnd + 1));
        $details = '';

        if ($remaining !== '') {
            if (!\str_starts_with($remaining, ':')) {
                return null;
            }

            $details = \trim(\substr($remaining, 1));
        }

        return (new Link())->urlTitle($title)
            ->url($url)
            ->urlDetails($details);
    }

    /**
     * Find the closing parenthesis of a Markdown link URL.
     *
     * Supports URLs holding balanced or escaped parentheses, e.g.
     *
     * https://example.com/foo(bar)
     * https://example.com/foo(a(b)c)
     * https://example.com/foo\)bar
     */
    private function findClosingParenthesis(string $line, int $start): ?int
    {
        $depth = 0;
        $length = \strlen($line);

        for ($index = $start; $index < $length; $index++) {
            $character = $line[$index];

            if ($character === '\\') {
                $index++;

                continue;
            }

            if ($character === '(') {
                $depth++;

                continue;
            }

            if ($character === ')') {
                if ($depth === 0) {
                    return $index;
                }

                $depth--;
            }
        }

        return null;
    }
}
