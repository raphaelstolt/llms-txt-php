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
        $fenced = $this->fencedLines($lines);
        $lineCount = \count($lines);

        $llmsTxt = new LlmsTxt();

        $index = $this->skipBlankLines($lines, 0);

        // H1 title.
        if ($index < $lineCount && $fenced[$index] === false) {
            $title = $this->headingContent($lines[$index], 1);

            if ($title !== null && $title !== '') {
                $llmsTxt->title($title);
                $index++;
            }
        }

        $index = $this->skipBlankLines($lines, $index);

        // Optional blockquote summary, all of its lines form the summary.
        $summary = [];

        while ($index < $lineCount && $fenced[$index] === false && $this->isBlockquote($lines[$index])) {
            $summaryLine = $this->blockquoteContent($lines[$index]);

            if ($summaryLine !== '') {
                $summary[] = $summaryLine;
            }

            $index++;
        }

        if ($summary !== []) {
            $llmsTxt->description(\implode(' ', $summary));
        }

        // Everything up to the first H2 is the details section.
        $details = [];

        while ($index < $lineCount && !$this->isSectionHeadingAt($lines, $fenced, $index)) {
            $details[] = $lines[$index];
            $index++;
        }

        $llmsTxt->details($this->trimBlankLines($details));

        // H2 sections and their file lists.
        while ($index < $lineCount) {
            if (!$this->isSectionHeadingAt($lines, $fenced, $index)) {
                $index++;

                continue;
            }

            $section = $this->sectionFor($llmsTxt, (string) $this->headingContent($lines[$index], 2));
            $index++;

            while ($index < $lineCount && !$this->isSectionHeadingAt($lines, $fenced, $index)) {
                $link = $fenced[$index] ? null : $this->parseLink($lines[$index]);

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

    /**
     * @param list<string> $lines
     * @param list<bool> $fenced
     */
    private function isSectionHeadingAt(array $lines, array $fenced, int $index): bool
    {
        return $fenced[$index] === false && $this->isSectionHeading($lines[$index]);
    }

    private function isSectionHeading(string $line): bool
    {
        $name = $this->headingContent($line, 2);

        return $name !== null && $name !== '';
    }

    /**
     * Returns the content of an ATX heading of the given level, null when the line holds none.
     *
     * Up to three leading spaces are allowed and an optional closing sequence of number
     * signs is stripped, as the CommonMark specification has it.
     */
    private function headingContent(string $line, int $level): ?string
    {
        if (\preg_match('/^ {0,3}(#{1,6})(?:[ \t]+(.*?))?[ \t]*$/', $line, $matches) !== 1) {
            return null;
        }

        if (\strlen($matches[1]) !== $level) {
            return null;
        }

        $content = $matches[2] ?? '';

        return \trim(\preg_replace('/(?:^|[ \t])#+[ \t]*$/', '', $content) ?? $content);
    }

    private function isBlockquote(string $line): bool
    {
        return \preg_match('/^ {0,3}>/', $line) === 1;
    }

    private function blockquoteContent(string $line): string
    {
        return \trim(\preg_replace('/^ {0,3}> ?/', '', $line) ?? $line);
    }

    /**
     * Flags the lines held by a fenced code block, the fence lines included.
     *
     * The Markdown of a `llms.txt` file may well show a `llms.txt` file, a heading or a file
     * list entry inside a code fence therefore must not be taken for one of the document.
     *
     * @param list<string> $lines
     * @return list<bool>
     */
    private function fencedLines(array $lines): array
    {
        $fenced = [];
        $fence = null;

        foreach ($lines as $line) {
            $marker = $this->fenceMarker($line);

            if ($fence === null) {
                $fence = $marker;
                $fenced[] = $marker !== null;

                continue;
            }

            $fenced[] = true;

            if ($marker !== null
                && $marker['character'] === $fence['character']
                && $marker['length'] >= $fence['length']
                && $marker['info'] === ''
            ) {
                $fence = null;
            }
        }

        return $fenced;
    }

    /**
     * Returns the code fence a given line opens or closes, null when it is no fence line.
     *
     * @return array{character: string, length: int, info: string}|null
     */
    private function fenceMarker(string $line): ?array
    {
        if (\preg_match('/^ {0,3}(`{3,}|~{3,})(.*)$/', $line, $matches) !== 1) {
            return null;
        }

        $info = \trim($matches[2]);

        if ($matches[1][0] === '`' && \str_contains($info, '`')) {
            return null;
        }

        return [
            'character' => $matches[1][0],
            'length' => \strlen($matches[1]),
            'info' => $info,
        ];
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
     * of turned into an empty link. The `*` and `+` bullets Markdown knows next to
     * the `-` of the specification are accepted as well.
     */
    private function parseLink(string $line): ?Link
    {
        $line = \trim($line);

        if (\preg_match('/^[-*+][ \t]+\[/', $line) !== 1) {
            return null;
        }

        $titleStart = (int) \strpos($line, '[') + 1;
        $titleEnd = \strpos($line, '](', $titleStart);

        if ($titleEnd === false) {
            return null;
        }

        $title = \substr($line, $titleStart, $titleEnd - $titleStart);
        $urlStart = $titleEnd + 2;
        $urlEnd = $this->findClosingParenthesis($line, $urlStart);

        if ($urlEnd === null) {
            return null;
        }

        $url = $this->linkDestination(\substr($line, $urlStart, $urlEnd - $urlStart));
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
     * Returns the destination of a Markdown link, without its optional link title.
     *
     * A destination not wrapped in angle brackets holds no whitespace, whatever follows it
     * is the link title of the CommonMark specification, e.g. `- [Title](/url "Link title")`.
     * The angle brackets of a wrapped destination are stripped, they are no part of it.
     */
    private function linkDestination(string $destination): string
    {
        $destination = \trim($destination);

        if (\preg_match('/^<([^<>]*)>(?:[ \t]+(?:"[^"]*"|\'[^\']*\'|\([^()]*\)))?$/', $destination, $matches) === 1) {
            return $matches[1];
        }

        if (\preg_match('/^(\S*)[ \t]+(?:"[^"]*"|\'[^\']*\'|\([^()]*\))$/', $destination, $matches) === 1) {
            return $matches[1];
        }

        return $destination;
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
