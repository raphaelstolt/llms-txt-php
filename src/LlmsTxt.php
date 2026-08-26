<?php

declare(strict_types=1);

namespace Stolt\LlmsTxt;

use \Exception;
use Stolt\LlmsTxt\Parser\LlmsTxtParser;
use Stolt\LlmsTxt\Section\Link;
use Stolt\LlmsTxt\Validation\ValidationError;
use Stolt\LlmsTxt\Validation\ValidationResult;

final class LlmsTxt
{
    private bool $hasBeenParsed = false;

    const CONTENT_TYPE = 'text/llms.txt';

    const OPTIONAL_SECTION_NAME = 'Optional';

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
     * @return Section[]
     */
    public function getSections(): array
    {
        return $this->sections;
    }

    private string $description = '';

    private string $details = '';

    /** @var Section[] */
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

    /**
     * Expand linked documents into an XML LLM context file.
     *
     * All sections are expanded, set `$skipOptional` to leave the `Optional` section out.
     *
     * @param callable(string): string|null $fetcher
     */
    public function toLlmContext(bool $skipOptional = false, ?callable $fetcher = null): string
    {
        return (new LlmContext())->expand($this, $skipOptional, $fetcher);
    }

    /**
     * All sections are expanded, set `$skipOptional` to leave the `Optional` section out.
     *
     * @param callable(string): string|null $fetcher
     */
    public function toLlmContextFile(string $path, bool $skipOptional = false, ?callable $fetcher = null): bool
    {
        return \file_put_contents($path, $this->toLlmContext($skipOptional, $fetcher)) !== false;
    }

    /**
     * Expand linked documents into a llms-full.txt Markdown file.
     *
     * All sections are expanded, set `$skipOptional` to leave the `Optional` section out.
     *
     * @param callable(string): string|null $fetcher
     */
    public function toFull(bool $skipOptional = false, ?callable $fetcher = null): string
    {
        return (new LlmsFull())->expand($this, $skipOptional, $fetcher);
    }

    /**
     * All sections are expanded, set `$skipOptional` to leave the `Optional` section out.
     *
     * @param callable(string): string|null $fetcher
     */
    public function toFullFile(string $path, bool $skipOptional = false, ?callable $fetcher = null): bool
    {
        return \file_put_contents($path, $this->toFull($skipOptional, $fetcher)) !== false;
    }

    public function toEmbeddedInScriptTag(): string
    {
        return $this->asScriptTag();
    }

    public function asScriptTag(): string
    {
        return '<script type="' . self::CONTENT_TYPE . '">' .PHP_EOL . $this->toString() . PHP_EOL . '</script>' . PHP_EOL;
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
     * Validates a given llms.txt file content.
     *
     * The title is the only element the llms.txt specification requires, a missing one is
     * therefore the only validation error. The description, details, and file lists are
     * recommended, missing ones are reported as validation warnings.
     *
     * If `$detailed` is false (default), it returns a simple boolean.
     * If `$detailed` is true, returns a `ValidationResult` object with rich diagnostics.
     *
     * @param bool $detailed Whether to return a ValidationResult instead of a boolean.
     *
     * @see https://llmstxt.org/
     * @throws Exception
     * @return bool|ValidationResult
     */
    public function validate(bool $detailed = false): bool|ValidationResult
    {
        if (!$this->hasBeenParsed) {
            throw new Exception("The llms.txt file hasn't been parsed yet");
        }

        $result = new ValidationResult();

        if ($this->title === '') {
            $result->addError(new ValidationError('Missing title'));
        }

        if ($this->description === '') {
            $result->addWarning(new ValidationError('Missing description'));
        }

        if ($this->details === '') {
            $result->addWarning(new ValidationError('Missing details'));
        }

        if (\count($this->sections) === 0) {
            $result->addWarning(new ValidationError('Missing at least one section'));
        }

        if ($this->hasSectionLinks() === false) {
            $result->addWarning(new ValidationError('Missing at least one section link'));
        }

        return $detailed ? $result : $result->isValid();
    }

    private function hasSectionLinks(): bool
    {
        foreach ($this->sections as $section) {
            if (\count($section->getLinks()) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws Exception
     */
    public function parse(string $pathToFileOrLlmsTxtContent): LlmsTxt
    {
        return (new LlmsTxtParser())->parse(
            $this->extractContent($pathToFileOrLlmsTxtContent)
        );
    }

    /**
     * Discriminates between llms.txt content and a path to a llms.txt file.
     *
     * Content starts with the required H1 title, a path ends in `txt` or `md`,
     * anything else is undeterminable and therefore throws.
     *
     * @throws Exception
     */
    private function extractContent(string $input): string
    {
        if (\str_starts_with($input, '#') || \str_starts_with($input, LlmsTxtParser::BYTE_ORDER_MARK . '#')) {
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

    /**
     * Flags the instance as being the result of a parse, which `validate` requires.
     *
     * @internal
     */
    public function markAsParsed(): self
    {
        $this->hasBeenParsed = true;

        return $this;
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

    public function section(Section $section): self
    {
        return $this->addSection($section);
    }

    /**
     * Appends a section, an already present section of the same name is kept.
     */
    public function addSection(Section $section): self
    {
        if ($this->getSectionByName($section->getName()) === null) {
            $this->sections[] = $section;
        }

        return $this;
    }

    /**
     * @param array<array-key, mixed> $sections
     * @throws Exception
     */
    public function sections(array $sections): self
    {
        return $this->addSections($sections);
    }

    /**
     * Replaces all present sections with the given ones.
     *
     * The given array must only include instances of `Section`, anything else throws.
     *
     * @param array<array-key, mixed> $sections
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

    /**
     * Sets the `Optional` section.
     *
     * The section is named after the `Optional` convention, an already present
     * `Optional` section is replaced. Since v2 of the llms.txt specification the
     * `Optional` section no longer carries mechanical semantics, it stays a
     * convention for secondary links.
     *
     * @see https://llmstxt.org/changes.html
     */
    public function optional(Section $section): self
    {
        $section->name(self::OPTIONAL_SECTION_NAME);

        foreach ($this->sections as $index => $presentSection) {
            if ($presentSection->getName() === self::OPTIONAL_SECTION_NAME) {
                $this->sections[$index] = $section;

                return $this;
            }
        }

        $this->sections[] = $section;

        return $this;
    }

    /**
     * Returns the `Optional` section, or null when there is none.
     */
    public function getOptional(): ?Section
    {
        return $this->getSectionByName(self::OPTIONAL_SECTION_NAME);
    }

    public function initialise(): LlmsTxt
    {
        return (new LlmsTxt())->title('Init title')
            ->description('Init description')
            ->details('Init details')
            ->addSection((new Section())->name('Init section')->addLink((new Link())->urlTitle('Init URL')->url('http://init.org')));
    }
}
