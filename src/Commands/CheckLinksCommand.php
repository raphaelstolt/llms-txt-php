<?php

declare(strict_types=1);

namespace Stolt\LlmsTxt\Commands;

use Stolt\LlmsTxt\LlmsTxt;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CheckLinksCommand extends Command
{
    private LlmsTxt $llmsTxt;

    public function __construct(LlmsTxt $llmsTxt)
    {
        $this->llmsTxt = $llmsTxt;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('check-links');
        $description = 'Check links of a llms txt file';
        $this->setDescription($description);

        $llmsTxtFileDescription = 'The name of the llms txt file. Defaults to llms.txt';

        $this->addArgument(
            'llms-txt-file',
            InputArgument::OPTIONAL,
            $llmsTxtFileDescription
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $llmsTxtFileToAnalyse = (string)$input->getArgument('llms-txt-file');
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

        $links = [];

        foreach ($analysedLlmsTxt->getSections() as $section) {
            foreach ($section->getLinks() as $link) {
                $links[] = $link->getUrl();
            }
        }
        $curlHandles = [];

        $multiHandle = \curl_multi_init();

        foreach ($links as $i => $link) {
            $curlHandle = \curl_init($link);
            \curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
            \curl_setopt($curlHandle, CURLOPT_TIMEOUT, 10);
            \curl_setopt($curlHandle, CURLOPT_HEADER, true);
            \curl_setopt($curlHandle, CURLOPT_FOLLOWLOCATION, false);

            \curl_multi_add_handle($multiHandle, $curlHandle);
            $curlHandles[$i] = $curlHandle;
        }

        $running = null;

        do {
            $status = \curl_multi_exec($multiHandle, $running);
            if ($status === CURLM_OK) {
                \curl_multi_select($multiHandle);
            }
        } while ($running > 0 && $status === CURLM_OK);

        $responses = [];
        foreach ($curlHandles as $i => $curlHandle) {
            $responses[$i] = \curl_multi_getcontent($curlHandle);
            \curl_multi_remove_handle($multiHandle, $curlHandle);
            \curl_close($curlHandle);
        }

        \curl_multi_close($multiHandle);

        $unreachableUrls = [];
        foreach ($responses as $i => $response) {
            $httpCode = \curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
            if ($httpCode >= 400) {
                $unreachableUrls[] = $links[$i];
            }
        }

        if (\count($unreachableUrls) > 0) {
            foreach ($unreachableUrls as $unreachableUrl) {
                $message = \sprintf("The link <info>%s</info> is not reachable.", $unreachableUrl);
                $output->writeln($message);
            }

            return Command::FAILURE;
        }

        $output->writeln("All links are reachable.");

        return Command::SUCCESS;
    }
}
