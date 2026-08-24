<?php

declare(strict_types=1);

namespace Stolt\LlmsTxt;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Discoverability of llms.txt files and markdown page versions, as introduced by v2
 * of the llms.txt specification.
 *
 * @see https://llmstxt.org/
 */
final class Discovery
{
    const DESCRIBED_BY_RELATION = 'describedby';

    const ALTERNATE_RELATION = 'alternate';

    const MARKDOWN_TYPE = 'text/markdown';

    const FILE_NAME = 'llms.txt';

    /**
     * Renders the link relations of a page as HTML <link> elements.
     *
     * Empty URLs are left out, an empty string is returned when both are empty.
     */
    public function asLinkElements(string $llmsTxtUrl = '', string $markdownUrl = ''): string
    {
        $elements = [];

        if ($markdownUrl !== '') {
            $elements[] = '<link rel="' . self::ALTERNATE_RELATION . '" type="' . self::MARKDOWN_TYPE
                . '" href="' . $this->escapeHtml($markdownUrl) . '">';
        }

        if ($llmsTxtUrl !== '') {
            $elements[] = '<link rel="' . self::DESCRIBED_BY_RELATION . '" href="'
                . $this->escapeHtml($llmsTxtUrl) . '">';
        }

        if ($elements === []) {
            return '';
        }

        return \implode(PHP_EOL, $elements) . PHP_EOL;
    }

    /**
     * Renders the link relations of a page as a HTTP Link header value.
     *
     * The returned value excludes the `Link:` field name, so it can be handed straight to
     * `\header('Link: ' . $value)`. Empty URLs are left out.
     */
    public function asLinkHeader(string $llmsTxtUrl = '', string $markdownUrl = ''): string
    {
        $values = [];

        if ($markdownUrl !== '') {
            $values[] = '<' . $markdownUrl . '>; rel="' . self::ALTERNATE_RELATION
                . '"; type="' . self::MARKDOWN_TYPE . '"';
        }

        if ($llmsTxtUrl !== '') {
            $values[] = '<' . $llmsTxtUrl . '>; rel="' . self::DESCRIBED_BY_RELATION . '"';
        }

        return \implode(', ', $values);
    }

    /**
     * Returns the llms.txt URLs a given HTML content is described by.
     *
     * @return string[]
     */
    public function describedByUrls(string $html): array
    {
        return $this->filterByRelation($this->linksFromHtml($html), self::DESCRIBED_BY_RELATION);
    }

    /**
     * Returns the markdown versions a given HTML content links to.
     *
     * @return string[]
     */
    public function markdownAlternates(string $html): array
    {
        return $this->filterByRelation(
            $this->linksFromHtml($html),
            self::ALTERNATE_RELATION,
            self::MARKDOWN_TYPE
        );
    }

    /**
     * Returns the llms.txt URLs a given HTTP Link header value describes.
     *
     * @return string[]
     */
    public function describedByUrlsFromHeader(string $linkHeader): array
    {
        return $this->filterByRelation($this->parseLinkHeader($linkHeader), self::DESCRIBED_BY_RELATION);
    }

    /**
     * Returns the markdown versions a given HTTP Link header value points to.
     *
     * @return string[]
     */
    public function markdownAlternatesFromHeader(string $linkHeader): array
    {
        return $this->filterByRelation(
            $this->parseLinkHeader($linkHeader),
            self::ALTERNATE_RELATION,
            self::MARKDOWN_TYPE
        );
    }

    /**
     * Returns the markdown URL forms of a given URL, the appending form first.
     *
     * Both forms accepted by the specification are returned, appending `.md` to the URL and
     * swapping its extension for `.md`. A URL lacking a file name maps to its index document,
     * a file name without an extension has nothing to swap and therefore only one form.
     *
     * @return string[]
     */
    public function markdownUrls(string $url): array
    {
        [$origin, $path, $suffix] = $this->splitUrl($url);

        if ($path === '') {
            $path = '/';
        }

        if (\str_ends_with($path, '/')) {
            return [
                $origin . $path . 'index.html.md' . $suffix,
                $origin . $path . 'index.md' . $suffix,
            ];
        }

        $extension = $this->extension($path);

        if ($extension === '') {
            return [$origin . $path . '.md' . $suffix];
        }

        return [
            $origin . $path . '.md' . $suffix,
            $origin . \substr($path, 0, -\strlen($extension)) . 'md' . $suffix,
        ];
    }

    /**
     * Returns the llms.txt URLs covering a given URL, the most specific one first.
     *
     * A llms.txt file covers the pages under its path, therefore every parent path of the
     * given URL is a candidate.
     *
     * @return string[]
     */
    public function coveringUrls(string $url): array
    {
        [$origin, $path] = $this->splitUrl($url);

        $directory = $this->directory($path);
        $urls = [];

        while (true) {
            $urls[] = $origin . $directory . self::FILE_NAME;

            if ($directory === '/') {
                return $urls;
            }

            $directory = $this->directory(\rtrim($directory, '/'));
        }
    }

    /**
     * Returns the most specific of the given llms.txt URLs covering a given URL.
     *
     * The URL and the candidates need to be in the same form, either both absolute or both
     * relative to the same origin. Null is returned when no candidate covers the URL.
     *
     * @param string[] $candidateUrls
     */
    public function coveringUrl(string $url, array $candidateUrls): ?string
    {
        foreach ($this->coveringUrls($url) as $coveringUrl) {
            if (\in_array($coveringUrl, $candidateUrls, true)) {
                return $coveringUrl;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{url: string, parameters: array<string, string>}>
     */
    private function linksFromHtml(string $html): array
    {
        $dom = new DOMDocument();
        // Suppress warnings from malformed HTML
        @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);

        $nodes = (new DOMXPath($dom))->query('//link[@rel][@href]');

        if ($nodes === false) {
            return [];
        }

        $links = [];
        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $links[] = [
                'url' => $node->getAttribute('href'),
                'parameters' => [
                    'rel' => $node->getAttribute('rel'),
                    'type' => $node->getAttribute('type'),
                ],
            ];
        }

        return $links;
    }

    /**
     * @return array<int, array{url: string, parameters: array<string, string>}>
     */
    private function parseLinkHeader(string $linkHeader): array
    {
        $matched = \preg_match_all('/<([^>]*)>((?:\s*;\s*[^,;]+)*)/', $linkHeader, $matches, PREG_SET_ORDER);

        if ($matched === false || $matched === 0) {
            return [];
        }

        $links = [];
        foreach ($matches as $match) {
            $parameters = [];
            $rawParameters = \preg_split('/\s*;\s*/', \trim($match[2]), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            foreach ($rawParameters as $rawParameter) {
                $parts = \explode('=', $rawParameter, 2);

                if (\count($parts) === 2) {
                    $parameters[\strtolower(\trim($parts[0]))] = \trim($parts[1], " \t\"'");
                }
            }

            $links[] = ['url' => \trim($match[1]), 'parameters' => $parameters];
        }

        return $links;
    }

    /**
     * @param array<int, array{url: string, parameters: array<string, string>}> $links
     * @return string[]
     */
    private function filterByRelation(array $links, string $relation, string $type = ''): array
    {
        $urls = [];

        foreach ($links as $link) {
            $relations = \preg_split(
                '/\s+/',
                \strtolower(\trim($link['parameters']['rel'] ?? '')),
                -1,
                PREG_SPLIT_NO_EMPTY
            ) ?: [];

            if (!\in_array($relation, $relations, true)) {
                continue;
            }

            if ($type !== '' && \strtolower(\trim($link['parameters']['type'] ?? '')) !== $type) {
                continue;
            }

            if ($link['url'] !== '') {
                $urls[] = $link['url'];
            }
        }

        return $urls;
    }

    /**
     * Splits a given URL into its origin, its path, and its query and fragment.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function splitUrl(string $url): array
    {
        $suffix = '';
        $position = \strcspn($url, '?#');

        if ($position < \strlen($url)) {
            $suffix = \substr($url, $position);
            $url = \substr($url, 0, $position);
        }

        $origin = '';

        if (\preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*://[^/]*#', $url, $matches) === 1) {
            $origin = $matches[0];
            $url = \substr($url, \strlen($origin));
        }

        return [$origin, $url, $suffix];
    }

    /**
     * Returns the directory of a given path, including its trailing slash.
     */
    private function directory(string $path): string
    {
        $position = \strrpos($path, '/');

        return $position === false ? '/' : \substr($path, 0, $position + 1);
    }

    /**
     * Returns the extension of the last segment of a given path.
     */
    private function extension(string $path): string
    {
        $segment = \substr($path, \strrpos($path, '/') === false ? 0 : (int) \strrpos($path, '/') + 1);
        $position = \strrpos($segment, '.');

        if ($position === false || $position === 0) {
            return '';
        }

        return \substr($segment, $position + 1);
    }

    private function escapeHtml(string $value): string
    {
        return \htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
