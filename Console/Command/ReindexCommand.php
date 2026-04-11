<?php

declare(strict_types=1);

namespace Byte8\Horizon\Console\Command;

use Byte8\Horizon\Model\Indexer\ProductIndexer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ReindexCommand extends Command
{
    public function __construct(
        private readonly ProductIndexer $productIndexer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('horizon:reindex');
        $this->setDescription('Rebuild the Horizon AI product index (flat table)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Horizon: rebuilding product index...</info>');

        try {
            $count = $this->productIndexer->reindexAll();
            $output->writeln("<info>Done. Indexed {$count} products.</info>");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Reindex failed: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
