<?php

declare(strict_types=1);

namespace Stolt\LlmsTxt\Commands;

use Stolt\LlmsTxt\LlmsTxt;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ValidateCommand extends Command
{
    private LlmsTxt $llmsTxt;

    private const NOT_LLMS_TXT_FOUND_AT_URI = 'no llms txt file found at uri';

    public function __construct(LlmsTxt $llmsTxt)
    {
        $this->llmsTxt = $llmsTxt;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('validate');
        $description = 'Validate the given llms txt file';
        $this->setDescription($description);

        $llmsTxtFileDescription = 'The llms txt file to validate';

        $this->addArgument(
            'llms-txt-file',
            InputArgument::OPTIONAL,
            $llmsTxtFileDescription
        );

        $uriDescription = "The URI at which to look for a llms txt file";

        $this->addArgument(
            'uri',
            InputArgument::OPTIONAL,
            $uriDescription
        );
    }

    private function getLlmsTxtFileContentFromUrl(string $url): mixed
    {
        $curlHandle = \curl_init();
        \curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($curlHandle, CURLOPT_URL, $url);
        $data = \curl_exec($curlHandle);
        \curl_close($curlHandle);

        if (\str_contains($data, '404')) {
            return self::NOT_LLMS_TXT_FOUND_AT_URI;
        }

        return $data;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $llmsTxtFileToValidate = (string) $input->getArgument('llms-txt-file');

        if (\str_starts_with($llmsTxtFileToValidate, 'http')) {
            $remoteLlmTxtContent = $this->getLlmsTxtFileContentFromUrl($llmsTxtFileToValidate . DIRECTORY_SEPARATOR . 'llms.txt');
            $isRemoteLlmsTxtFile = true;

            if ($remoteLlmTxtContent === self::NOT_LLMS_TXT_FOUND_AT_URI) {
                $warning = \sprintf("Warning: No llms txt file found at the provided URI %s.", $llmsTxtFileToValidate);
                $outputContent = '<error>' . $warning . '</error>';
                $output->writeln($outputContent);

                return Command::FAILURE;
            }

            try {
                $parsedLlmsTxt = $this->llmsTxt->parse($remoteLlmTxtContent);
            } catch (\Exception $e) {
                $output->writeln(\sprintf('%sParse error: %s.%s', '<error>', $e->getMessage(), '</error>'));

                return Command::FAILURE;
            }

            if ($parsedLlmsTxt->validate()) {
                $response = \sprintf('The delivered llms txt file from %s is <info>valid</info>.', $llmsTxtFileToValidate);
                $output->writeln($response);

                return Command::SUCCESS;
            }

            $response = \sprintf('The delivered llms txt file from %s is <info>valid</info>.', $llmsTxtFileToValidate);
            $output->writeln($response);

            return Command::FAILURE;
        } else {
            if (\file_exists($llmsTxtFileToValidate) === false) {
                $warning = \sprintf("Warning: The provided llms txt file %s does not exists.", $llmsTxtFileToValidate);
                $outputContent = '<error>' . $warning . '</error>';
                $output->writeln($outputContent);

                return Command::FAILURE;
            }

            $parsedLlmsTxt = $this->llmsTxt->parse($llmsTxtFileToValidate);

            if ($parsedLlmsTxt->validate()) {
                $response = \sprintf('The provided llms txt file %s is <info>valid</info>.', $llmsTxtFileToValidate);
                $output->writeln($response);

                return Command::SUCCESS;
            }

            $response = \sprintf('The provided llms txt file %s is <error>invalid</error>.', $llmsTxtFileToValidate);
            $output->writeln($response);

            return Command::FAILURE;
        }
    }
}
