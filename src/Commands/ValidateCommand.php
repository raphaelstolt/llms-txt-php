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
            InputArgument::REQUIRED,
            $llmsTxtFileDescription
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $llmsTxtFileToValidate = (string) $input->getArgument('llms-txt-file');

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
