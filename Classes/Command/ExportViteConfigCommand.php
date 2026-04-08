<?php

declare(strict_types=1);

namespace Tenryuubito\Alice\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Command to export Vite entry points from TYPO3 configuration to JSON.
 * 
 * This command facilitates the communication between TYPO3 extension configuration
 * and the external Vite build process.
 */
class ExportViteConfigCommand extends Command
{
    /**
     * Configures the command.
     */
    protected function configure(): void
    {
        $this->setDescription('Exports Vite entries and environment paths from TYPO3 to JSON for the build process.')
             ->addOption('flush-cache', 'f', InputOption::VALUE_NONE, 'Flush TYPO3 file cache before export');
    }

    /**
     * Executes the configuration export.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Alice: Exporting Environment-Aware Build Configuration');

        try {
            $publicPath = str_replace('\\', '/', Environment::getPublicPath());
            $projectPath = str_replace('\\', '/', Environment::getProjectPath());
            $varPath = str_replace('\\', '/', Environment::getVarPath());

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
            $additionalEntries = array_unique((array)($settings['additional_entries'] ?? []));
            
            $manifest = [
                'publicPath' => $publicPath,
                'projectPath' => $projectPath,
                'entries' => []
            ];

            $manifest['entries']['packages/alice/Resources/Public/Build/JavaScript/Backend'] = str_replace('\\', '/', (string)GeneralUtility::getFileAbsFileName('EXT:alice/Resources/Private/TypeScript/Backend.ts'));
            $manifest['entries']['packages/alice/Resources/Public/Build/JavaScript/AuditRunner'] = str_replace('\\', '/', (string)GeneralUtility::getFileAbsFileName('EXT:alice/Resources/Private/TypeScript/AuditRunner.ts'));

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
     * Determines the output key relative to the extension's Resources/Public folder.
     *
     * @param string $absPath Absolute file path
     * @param string $publicPath Public path
     * @param string $projectPath Project root path
     * @return string
     */
    private function determineOutputKey(string $absPath, string $publicPath, string $projectPath): string
    {
        $match = [];
        if (preg_match('/packages\/([^\/]+)\/Resources\/Private\/TypeScript\/(.+)$/', $absPath, $match)) {
            return 'packages/' . $match[1] . '/Resources/Public/Build/JavaScript/' . preg_replace('/\.(ts|js|scss)$/', '', $match[2]);
        }

        $relPath = str_replace($projectPath . '/', '', $absPath);
        
        return preg_replace('/Resources\/Private\/TypeScript/', 'Resources/Public/Build/JavaScript', preg_replace('/\.(ts|js|scss)$/', '', $relPath));
    }
}
