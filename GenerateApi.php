<?php

namespace Dparadiz\Codegen;

use cebe\openapi\exceptions\IOException;
use cebe\openapi\Reader;
use cebe\openapi\ReferenceContext;
use cebe\openapi\spec\OpenApi;
use Dparadiz\Codegen\CodeWriter\MustacheEngineAdapter;
use Dparadiz\Codegen\Encoder\OpenApiToPsr15;
use Dparadiz\Codegen\Generator\StackProcessor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class GenerateApi extends Command
{

    protected function configure()
    {
        $defaultTemplatePath = realpath(__DIR__ . '/Resources/templates');

        $this->setDescription('Creates api operation handlers based on openapi specification and templates')
            ->addOption('open-api-spec', 's', InputOption::VALUE_REQUIRED, 'Specification json file')
            ->addOption('output-folder', 'o', InputOption::VALUE_REQUIRED, 'Output folder')
            ->addOption(
                'templates',
                't',
                InputOption::VALUE_REQUIRED,
                'Templates for code generation',
                $defaultTemplatePath
            )
            ->addOption('namespace', null, InputOption::VALUE_OPTIONAL, 'Namespace. Default Api', 'Api');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {

        $fileName = $input->getOption('open-api-spec');
        $fileContent = file_get_contents($fileName, false, stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false,],]));
        if ($fileContent === false) {
            $e = new IOException("Failed to read file: '$fileName'");
            $e->fileName = $fileName;
            throw $e;
        }
        $spec = Reader::readFromJson($fileContent, OpenApi::class);
        $context = new ReferenceContext($spec, $fileName);
        $spec->setReferenceContext($context);


        $stack = (new OpenApiToPsr15\Encoder())->encode($spec);

        $stackProcessor = new StackProcessor(
            new MustacheEngineAdapter((string)$input->getOption('templates')),
            $input->getOption('output-folder')
        );

        $stackProcessor->process($stack);

        $output->writeln('Code generation complete');
        return Command::SUCCESS;
    }

}
