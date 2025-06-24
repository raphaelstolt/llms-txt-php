<?php

declare(strict_types=1);

namespace Stolt\LlmsTxt;

use \Exception;
use Stolt\LlmsTxt\Section\Link;

class LlmsTxt
{
    private string $title = '';

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return string
     */
    public function getDetails(): string
    {
        return $this->details;
    }

    /**
     * @return array
     */
    public function getSections(): array
    {
        return $this->sections;
    }
    private string $description = '';
    private string $details = '';
    private array $sections = [];

    public function __construct()
    {
    }

    public function title(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function details(string $details): self
    {
        $this->details = $details;
        return $this;
    }

    public function toFile(string $path): bool
    {
        return file_put_contents($path, $this->toString()) !== false;
    }

    public function toString(): string
    {
        $llmsTxtString = "# " .  $this->title . PHP_EOL . PHP_EOL;

        if ($this->description) {
            $llmsTxtString .= "> " . $this->description . PHP_EOL . PHP_EOL;
        }

        if ($this->details) {
            $llmsTxtString .= $this->details . PHP_EOL;
        }

        if (\count($this->sections) > 0) {
            foreach ($this->sections as $section) {
                $llmsTxtString .= PHP_EOL . $section->toString() . PHP_EOL;
            }
        }

        return $llmsTxtString;
    }

    public function parse(string $pathToFile): LlmsTxt
    {
        $llmsTxtContent = \file_get_contents($pathToFile);

        if ($llmsTxtContent === false) {
            throw new Exception('Unable to read llms.txt file ' . $pathToFile);
        }

        $lines = \explode(PHP_EOL, $llmsTxtContent);
        $llmsTxt = new LlmsTxt();

        foreach ($lines as $index => $line) {

            if (\str_contains($line, '## ')) {
                $section = (new Section)->name(\str_replace(['## '], '', $line));
                continue;
            }

            if (\str_contains($line, '- ')) {
                $urlParts = \explode(': ', $line, );
                if (\count($urlParts) === 2) {
                    $urlTitleParts = \explode('[', $urlParts[0]);
                    $urlTitleParts = \explode('](', $urlTitleParts[1]);
                    $url = \str_replace(')', '', $urlTitleParts[1]);
                    $link = (new Link)->url($url)->urlTitle($urlTitleParts[0])->urlDetails($urlParts[1]);
                    $section->addLink($link);
                    $llmsTxt->addSection($section);
                    continue;
                } else {
                    $urlTitleParts = \explode('[', $urlParts[0]);
                    $urlTitleParts = \explode('](', $urlTitleParts[1]);
                    $url = \str_replace(')', '', $urlTitleParts[1]);
                    $link = (new Link)->url($url)->urlTitle($urlTitleParts[0]);
                    $section->addLink($link);
                    $llmsTxt->addSection($section);
                    continue;
                }
            }

            if (\str_contains($line, '# ')) {
                $title = \str_replace(['# '], '', $line);
                $llmsTxt->title($title);
            }

            if (\str_contains($line, '> ')) {
                $description = \str_replace(['> '], '', $line);
                $llmsTxt->description($description);
            }

            if (\is_string($line) && $line !== '' && !\str_starts_with($line, '#') && !\str_starts_with($line, '>')) {
                $llmsTxt->details($line);
            }
        }

        return $llmsTxt;
    }

    public function addSection(Section $section): self
    {
        \array_push($this->sections, $section);

        return $this;
    }
}
