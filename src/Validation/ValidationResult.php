<?php

declare(strict_types=1);

namespace Stolt\LlmsTxt\Validation;

final class ValidationResult
{
    /** @var ValidationError[] */
    private array $errors = [];

    /** @var ValidationError[] */
    private array $warnings = [];

    public function addError(ValidationError $error): void
    {
        $this->errors[] = $error;
    }

    /**
     * Adds a violation of a recommended, but not specification required, element.
     */
    public function addWarning(ValidationError $warning): void
    {
        $this->warnings[] = $warning;
    }

    public function isValid(): bool
    {
        return empty($this->errors);
    }

    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /**
     * @return ValidationError[]
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return ValidationError[]
     */
    public function warnings(): array
    {
        return $this->warnings;
    }
}
