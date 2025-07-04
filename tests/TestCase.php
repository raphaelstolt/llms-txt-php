<?php

declare(strict_types=1);

namespace Stolt\LlmsTxt\Tests;

use PHPUnit\Framework\TestCase as PHPUnit;

/**
 * @not-fix
 */
class TestCase extends PHPUnit
{
    protected string $temporaryDirectory = '';

    protected function tearDown(): void
    {
        if ($this->temporaryDirectory !== '') {
            $this->removeDirectory($this->temporaryDirectory);
        }
    }

    protected function removeDirectory(string $directory): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        /** @var \SplFileInfo $fileinfo */
        foreach ($files as $fileinfo) {
            if ($fileinfo->isDir()) {
                @\rmdir($fileinfo->getRealPath());
                continue;
            }
            @\unlink($fileinfo->getRealPath());
        }

        @\rmdir($directory);
    }

    protected function setUpTemporaryDirectory(): void
    {
        if ($this->isWindows() === false) {
            \ini_set('sys_temp_dir', '/tmp/llms');
            $this->temporaryDirectory = '/tmp/llms';
        } else {
            $this->temporaryDirectory = \sys_get_temp_dir()
                . DIRECTORY_SEPARATOR
                . 'llms';
        }

        if (!\file_exists($this->temporaryDirectory)) {
            \mkdir($this->temporaryDirectory);
        }
    }

    protected function isWindows(string $os = PHP_OS): bool
    {
        if (\strtoupper(\substr($os, 0, 3)) !== 'WIN') {
            return false;
        }

        return true;
    }
}
