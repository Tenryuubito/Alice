<?php

/**
 * This file is part of the "Alice" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 *
 * (c) 2026 Tenryuubito
 */


declare(strict_types=1);

namespace Tenryuubito\Alice\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Process;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Cache\CacheManager;

/**
 * Command to manage Alice Node.js assets (install, dev, build)
 */
class AssetBundleCommand extends Command
{
    protected function configure(): void
    {
        $this->setDescription('Handles Node.js asset management for the Alice extension.')
             ->addOption('setup', 's', InputOption::VALUE_NONE, 'Run npm install')
             ->addOption('dev', 'd', InputOption::VALUE_NONE, 'Run npm run dev (Vite Dev Server)')
             ->addOption('build', 'b', InputOption::VALUE_NONE, 'Run npm run build (Production Build)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        // Use a relative path from this class instead of EXT: lookup for better CLI compatibility
        $alicePath = dirname(__DIR__, 2);
        
        if (!is_dir($alicePath) || !file_exists($alicePath . '/package.json')) {
            $io->error('Could not locate the Alice extension directory or its package.json at: ' . $alicePath);
            return Command::FAILURE;
        }

        $command = '';
        if ($input->getOption('setup')) {
            $command = 'npm install';
            $io->title('Alice: Running Node Setup');
        } elseif ($input->getOption('dev')) {
            $command = 'npm run dev';
            $io->title('Alice: Starting Development Server');
        } elseif ($input->getOption('build')) {
            $command = 'npm run build';
            $io->title('Alice: Generating Production Build');
        } else {
            $io->warning('Please provide an option: --setup, --dev, or --build');
            return Command::INVALID;
        }

        $io->note('Executing in: ' . $alicePath);

        $process = Process::fromShellCommandline($command, $alicePath);
        $process->setTimeout(null); // No timeout for long-running dev/setup tasks
        
        try {
            $process->run(function ($type, $buffer) use ($output) {
                $output->write($buffer);
            });

            if (!$process->isSuccessful()) {
                $io->error('The command failed to execute correctly.');
                return Command::FAILURE;
            }

            if ($input->getOption('build')) {
                $io->info('Flushing TYPO3 system caches...');
                GeneralUtility::makeInstance(CacheManager::class)->flushCaches();
                $io->success('Task completed and caches flushed successfully.');
            } else {
                $io->success('Task completed successfully.');
            }
            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $io->error('Process Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
