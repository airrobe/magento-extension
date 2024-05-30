<?php
/** @noinspection PhpMissingClassConstantTypeInspection */

namespace AirRobe\TheCircularWardrobe\Console\Command;

use AirRobe\TheCircularWardrobe\Helper\Data;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SyncTaxonomy
 * @package AirRobe\TheCircularWardrobe\Console\Command
 * @noinspection PhpUnused
 */
class SyncTaxonomy extends Command
{
    const NAME = 'name';

    protected Data $helperData;

    public function __construct(
      Data $helper,
      string $name = null
    ) {
      $this->helperData = $helper;
      parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('airrobe:taxonomy:sync');
        $this->setDescription('This is my first console command.');
        $this->addOption(
            self::NAME,
            null,
            InputOption::VALUE_REQUIRED,
            'Name'
        );

        parent::configure();
    }

    /**
     * Execute the command
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        try {
            $response = $this->helperData->sendToAirRobeAPI(
                [
                    'query' => "mutation MagentoPrepareMappings(\$input: PrepareMappingsMutationInput!){
                        prepareMappings(input: \$input) { error  }
                    }",
                    'variables' => [
                        'input' => [
                            'productTypes' => $this->helperData->getAllCategoryTrees(),
                            'customFields' => $this->helperData->getAllAttributes()
                        ]
                    ]
                ]
            );

            $output->writeln('<comment>Result of taxonomy sync: ' . $response . '</comment>');
        } catch (Exception $e) {
            $output->writeln(
                '<error> ' . sprintf(
                    'Data sync task failed with error #%d: %s',
                    $e->getCode(),
                    $e->getMessage()
                ) .  '</error>'
            );
        }
    }
}
