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
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Input\InputOption;

/**
 * Command to export Vite entry points from TYPO3 configuration to JSON
 */
class ExportViteConfigCommand extends Command
{
    protected function configure(): void
    {
        $this->setDescription('Exports Vite entries and environment paths from TYPO3 to JSON for the build process.')
             ->addOption('flush-cache', 'f', InputOption::VALUE_NONE, 'Flush TYPO3 file cache before export');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Alice: Exporting Environment-Aware Build Configuration');

        try {
            $publicPath = str_replace('\\', '/', Environment::getPublicPath());
            $projectPath = str_replace('\\', '/', Environment::getProjectPath());
            $varPath = str_replace('\\', '/', Environment::getVarPath());

            // Handle Cache Flushing (Replaces "rm -rf ../../../var/cache/*")
            if ($input->getOption('flush-cache')) {
                $io->note('Flushing TYPO3 cache directory...');
                $cacheDir = $varPath . '/cache';
                if (is_dir($cacheDir)) {
                    $fs = new Filesystem();
                    $fs->remove($cacheDir);
                    $fs->mkdir($cacheDir);
                    $io->success('Cache directory cleared.');
                }
            }
            
            $settings = (array)($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['alice'] ?? []);
            $additionalEntries = (array)($settings['additional_entries'] ?? []);
            
            $manifest = [
                'publicPath' => $publicPath,
                'projectPath' => $projectPath,
                'entries' => []
            ];

            // 1. Add Alice Core Entries (Absolute)
            $manifest['entries']['packages/alice/Resources/Public/Build/JavaScript/Backend'] = str_replace('\\', '/', (string)GeneralUtility::getFileAbsFileName('EXT:alice/Resources/Private/TypeScript/Backend.ts'));
            $manifest['entries']['packages/alice/Resources/Public/Build/JavaScript/AuditRunner'] = str_replace('\\', '/', (string)GeneralUtility::getFileAbsFileName('EXT:alice/Resources/Private/TypeScript/AuditRunner.ts'));

            // 2. Add Dynamic Entries
            foreach ($additionalEntries as $rawEntry) {
                $rawEntry = (string)$rawEntry;
                if (str_starts_with($rawEntry, 'EXT:')) {
                    $absPath = GeneralUtility::getFileAbsFileName($rawEntry);
                    if (!empty($absPath) && file_exists($absPath)) {
                        $absPath = str_replace('\\', '/', $absPath);
                        $outputKey = $this->determineOutputKey($absPath, $publicPath, $projectPath);
                        $manifest['entries'][$outputKey] = $absPath;
                        $io->note('Targeted entry: ' . $outputKey . ' -> ' . $absPath);
                    }
                }
            }

            $outputPath = GeneralUtility::getFileAbsFileName('EXT:alice/vite.entries.json');
            file_put_contents($outputPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            
            $io->success('Vite manifest exported to: ' . $outputPath);
            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $io->error('Failed to export manifest: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Determines output key relative to the extension's Resources/Public folder
     */
    private function determineOutputKey(string $absPath, string $publicPath, string $projectPath): string
    {
        // Find "Resources/Private/TypeScript" or similar in path
        $match = [];
        if (preg_match('/packages\/([^\/]+)\/Resources\/Private\/TypeScript\/(.+)$/', $absPath, $match)) {
            return 'packages/' . $match[1] . '/Resources/Public/Build/JavaScript/' . preg_replace('/\.(ts|js|scss)$/', '', $match[2]);
        }

        // Fallback: try to relative it to project root
        $relPath = str_replace($projectPath . '/', '', $absPath);
        
        return preg_replace('/Resources\/Private\/TypeScript/', 'Resources/Public/Build/JavaScript', preg_replace('/\.(ts|js|scss)$/', '', $relPath));
    }
}
