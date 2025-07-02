<?php

declare(strict_types=1);

namespace Stolt\LlmsTxt\Commands;

use Stolt\LlmsTxt\LlmsTxt;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class InfoCommand extends Command
{
    private LlmsTxt $llmsTxt;

    public function __construct(LlmsTxt $llmsTxt)
    {
        $this->llmsTxt = $llmsTxt;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('info');
        $description = 'Get metadata info of a llms txt file';
        $this->setDescription($description);

        $llmsTxtFileDescription = 'The name of the llms txt file to analyse. Defaults to llms.txt';

        $this->addArgument(
            'llms-txt-file',
            InputArgument::OPTIONAL,
            $llmsTxtFileDescription
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $llmsTxtFileToAnalyse = (string) $input->getArgument('llms-txt-file');
        if ($llmsTxtFileToAnalyse === '') {
            $llmsTxtFileToAnalyse = 'llms.txt';
        }

        $llmsTxtFileFullname = \getcwd() . DIRECTORY_SEPARATOR . $llmsTxtFileToAnalyse;

        if (\file_exists($llmsTxtFileFullname) === false) {
            $warning = \sprintf("Warning: The provided llms txt file %s does not exists.", \basename($llmsTxtFileFullname));
            $outputContent = '<error>' . $warning . '</error>';
            $output->writeln($outputContent);

            return Command::FAILURE;
        }

        $analysedLlmsTxt = $this->llmsTxt->parse($llmsTxtFileFullname);

        $amountOfSectionLinks = 0;

        foreach ($analysedLlmsTxt->getSections() as $section) {
            $amountOfSectionLinks+= \count($section->getLinks());
        }

        $analysedLlmsTxtData = [
            'sections' => \count($analysedLlmsTxt->getSections()),
            'links' => $amountOfSectionLinks,
            'last_modification' => \date('d.m.Y H:i:s', \filemtime($llmsTxtFileFullname)),
            'file' => [
                'name' => $llmsTxtFileToAnalyse,
                'path' => $llmsTxtFileFullname,
                'size' => $this->humanFilesize(\filesize($llmsTxtFileFullname)),
            ]
        ];

        $output->writeln(\json_encode($analysedLlmsTxtData, JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }

    private function humanFilesize(int $bytes, int $decimals = 2)
    {
        $sz = 'BKMGTP';
        $factor = \floor((\strlen((string) $bytes) - 1) / 3);

        return \sprintf("%.{$decimals}f", $bytes / \pow(1024, $factor)) . @$sz[$factor];
    }
}
