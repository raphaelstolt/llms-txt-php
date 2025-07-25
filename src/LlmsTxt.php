<?php

declare(strict_types=1);

namespace Stolt\LlmsTxt;

use \Exception;
use Stolt\LlmsTxt\Section\Link;

final class LlmsTxt
{
    private bool $hasBeenParsed = false;

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
        return \file_put_contents($path, $this->toString()) !== false;
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

    /**
     * @throws Exception
     */
    public function validate(): bool
    {
        if ($this->hasBeenParsed) {
            if ($this->title !== '' && $this->description !== '' && $this->details !== '' && \count($this->sections) > 0 && \count($this->sections[0]->getLinks()) > 0) {
                return true;
            }

            return false;
        }

        throw new Exception("The llms.txt file hasn't been parsed yet");
    }

    /**
     * @throws Exception
     */
    public function parse(string $pathToFileOrLlmsTxtContent): LlmsTxt
    {
        $llmsTxtContent = $this->extractContent($pathToFileOrLlmsTxtContent);
        $lines = $this->normalizeLines($llmsTxtContent);

        $llmsTxt = new LlmsTxt();
        $section = null;
        $detailsBuffer = '';

        foreach ($lines as $line) {
            $trimmedLine = \trim($line);

            if ($trimmedLine === '') {
                continue;
            }

            if (\str_starts_with($trimmedLine, '# ')) {
                $llmsTxt->title(\substr($trimmedLine, 2));
                continue;
            }

            if (\str_starts_with($trimmedLine, '## ')) {
                $sectionName = \substr($trimmedLine, 3);
                $section = new Section();
                $section->name($sectionName);

                if ($llmsTxt->getSectionByName($sectionName) === null) {
                    $llmsTxt->addSection($section);
                }
                continue;
            }

            if (\str_starts_with($trimmedLine, '> ')) {
                $llmsTxt->description(\substr($trimmedLine, 2));
                continue;
            }

            if (\str_starts_with($trimmedLine, '- ')) {
                $link = $this->parseLinkLine($trimmedLine);

                if ($section !== null) {
                    $llmsTxt->getSectionByName($section->getName())->addLink($link);
                }

                continue;
            }

            $detailsBuffer .= $trimmedLine . ' ';
            $llmsTxt->details(\trim($detailsBuffer));
        }

        $llmsTxt->hasBeenParsed = true;

        return $llmsTxt;
    }

    /**
     * @throws Exception
     */
    private function extractContent(string $input): string
    {
        if (\str_starts_with($input, '#')) {
            return $input;
        }

        if (\str_ends_with($input, 'txt') || \str_ends_with($input, 'md')) {
            $content = @\file_get_contents($input);

            if ($content === false) {
                throw new Exception("Unable to read llms.txt file at path {$input}");
            }

            return $content;
        }

        throw new Exception('Unable to determine if input is a path to file or llms.txt content');
    }

    private function normalizeLines(string $content): array
    {
        $normalized = \str_replace(['  ', PHP_EOL . PHP_EOL], ['', PHP_EOL], $content);
        return \explode(PHP_EOL, $normalized);
    }

    private function parseLinkLine(string $line): Link
    {
        $urlParts = \explode(': ', $line, 2);
        \preg_match('/\[(.*?)\]\((.*?)\)/', $urlParts[0], $matches);

        $link = new Link();
        if (\count($matches) === 3) {
            $link->url($matches[2])->urlTitle($matches[1]);
        }

        if (\count($urlParts) === 2) {
            $link->urlDetails($urlParts[1]);
        }

        return $link;
    }

    public function getSectionByName(string $name): ?Section
    {
        if (\count($this->sections) === 0) {
            return null;
        }

        foreach ($this->sections as $section) {
            if ($section->getName() === $name) {
                return $section;
            }
        }

        return null;
    }

    public function addSection(Section $section): self
    {
        if (\count($this->sections) === 0) {
            $this->sections[] = $section;
        }

        if ($this->getSectionByName($section->getName()) === null) {
            $this->sections[] = $section;
        }

        return $this;
    }

    /**
     * @throws Exception
     */
    public function addSections(array $sections): self
    {
        foreach ($sections as $section) {
            if (!$section instanceof Section) {
                throw new Exception('Sections must only include instances of Section');
            }
        }

        $this->sections = $sections;

        return $this;
    }

    public function initialise(): LlmsTxt
    {
        $llmsTxt = (new LlmsTxt())->title('Init title')->description('Init description')
            ->details('Init details')
            ->addSection((new Section())->name('Init section')->addLink((new Link())->urlTitle('Init URL')->url('http://init.org')));

        return $llmsTxt;
    }
}
