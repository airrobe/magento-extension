<?php

namespace AirRobe\TheCircularWardrobe\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use AirRobe\TheCircularWardrobe\Helper;

/**
 * Class SomeCommand
 */
class SyncTaxonomy extends Command
{
    const NAME = 'name';

    /**
     * @inheritDoc
     */
    protected function configure()
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
     * @return null|int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $helper = $objectManager->create('AirRobe\TheCircularWardrobe\Helper\Data');

        try {
            $response = $helper->sendToAirRobeAPI(
                [
                    'query' => "mutation MagentoPrepareMappings(\$input: PrepareMappingsMutationInput!){
                        prepareMappings(input: \$input) { error  }
                    }",
                    'variables' => [
                        'input' => [
                            'productTypes' => $helper->getAllCategoryTrees(),
                            'customFields' => $helper->getAllAttributes()
                        ]
                    ]
                ]
            );

            $output->writeln('<comment>Result of taxonomy sync: ' . $response . '</comment>');
        } catch (\Exception $e) {
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
