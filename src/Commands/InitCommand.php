<?php

declare(strict_types=1);

namespace Stolt\LlmsTxt\Commands;

use Stolt\LlmsTxt\LlmsTxt;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class InitCommand extends Command
{
    private LlmsTxt $llmsTxt;

    public function __construct(LlmsTxt $llmsTxt)
    {
        $this->llmsTxt = $llmsTxt;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('init');
        $description = 'Create an initial llms txt file';
        $this->setDescription($description);

        $llmsTxtFileDescription = 'The name of the llms txt file to initialise. Defaults to llms.txt';

        $this->addArgument(
            'llms-txt-file',
            InputArgument::OPTIONAL,
            $llmsTxtFileDescription
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $llmsTxtFileToInitialise = (string) $input->getArgument('llms-txt-file');
        if ($llmsTxtFileToInitialise === '') {
            $llmsTxtFileToInitialise = 'llms.txt';
        }

        $initialisedLlmsTxt = $this->llmsTxt->initialise();

        if ($initialisedLlmsTxt->getTitle() !== '') {
            if (\file_exists(\getcwd() . DIRECTORY_SEPARATOR . $llmsTxtFileToInitialise)) {
                $response = \sprintf('The llms txt file <info>%s</info> already exists.', $llmsTxtFileToInitialise);
                $output->writeln($response);

                return Command::FAILURE;
            }

            \file_put_contents(\getcwd() . DIRECTORY_SEPARATOR . $llmsTxtFileToInitialise, $initialisedLlmsTxt->toString());

            $response = \sprintf('Created llms txt file <info>%s</info>.', $llmsTxtFileToInitialise);
            $output->writeln($response);

            return Command::SUCCESS;
        }

        $response = \sprintf('Unable to create llms txt file <info>%s</info>.', $llmsTxtFileToInitialise);
        $output->writeln($response);

        return Command::FAILURE;
    }
}
