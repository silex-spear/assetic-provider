<?php

namespace Spear\Silex\Provider\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Puzzle\Configuration;
use SilexAssetic\Assetic\Dumper;
use Symfony\Component\Console\Input\InputOption;

class AsseticDumper extends Command
{
    const
        NAME = 'assetic:dump';

    private
        $input,
        $output,
        $configuration,
        $dumper,
        $targetDirectoryPath;

    public function __construct(Configuration $config, Dumper $dumper, $targetDirectoryPath)
    {
        parent::__construct(self::NAME);

        $this->configuration = $config;
        $this->dumper = $dumper;
        $this->targetDirectoryPath = $this->enforceEndingSlash($targetDirectoryPath);
    }

    protected function configure()
    {
        $this->setName(self::NAME)
            ->setDescription('Dump assets to the filesystem')
            ->addOption('overwrite', 'o', InputOption::VALUE_NONE, 'Overwrite already dumped files')
            ->addOption('only-extra', null, InputOption::VALUE_NONE, 'Dump only extra assets');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $startTime = microtime(true);
        if(! $input->getOption('only-extra'))
        {
            $this->output("<comment>Dumping assets...</comment>");

            $this->dumper->addTwigAssets();
            $this->dumper->dumpAssets();
        }

        $this->output('<comment>Dumping extra directories</comment>');
        $this->dumpExtraDirectories();
        $this->output('<comment>Dumping extra files</comment>');
        $this->dumpExtraFiles();

        $stopTime = microtime(true);
        $duration = number_format($stopTime - $startTime, 3);
        $this->output(sprintf('<comment>Done is </comment>%s<comment> seconds.</comment>', $duration));
    }

    private function dumpExtraFiles()
    {
        $files = $this->configuration->read('assetic/dumper/files', array());

        foreach($files  as $source => $target)
        {
            $fileInfo = new \SplFileInfo($source);
            $targetPath = $this->enforceEndingSlash($this->targetDirectoryPath . $target);

            $this->dumpFile($fileInfo, $targetPath);
        }
    }

    private function dumpExtraDirectories()
    {
        $directories = $this->configuration->read('assetic/dumper/directories', array());

        foreach($directories as $source => $target)
        {
            $source = $this->enforceEndingSlash($source);
            $targetPath = $this->enforceEndingSlash($this->targetDirectoryPath . $target);

            $this->ensureDirectoryExists($targetPath);

            $this->output("<comment>Copy </comment>$source<comment> into </comment>$targetPath", OutputInterface::VERBOSITY_VERBOSE);
            $this->dumpDirectory($source, $targetPath);
        }
    }

    private function ensureDirectoryExists($path)
    {
        if(! is_dir($path))
        {
            if(@mkdir($path, 0755, true) === false)
            {
                throw new \InvalidArgumentException("$path is not a valid target directory");
            }
        }
    }

    private function dumpDirectory($sourceDirectory, $targetPath)
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $sourceDirectory,
                \FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::SKIP_DOTS
            ),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach($iterator as $fileInfo)
        {
            $this->dumpFile($fileInfo, $targetPath);
        }
    }

    private function dumpFile(\SplFileInfo $fileInfo, $targetPath)
    {
        $sourceFilePath = $fileInfo->getPathname();
        if(! is_file($sourceFilePath))
        {
            throw new \RuntimeException("$sourceFilePath does not exist");
        }

        $sourceDirectory = $this->enforceEndingSlash(dirname($sourceFilePath));
        $sourceRelativePath = str_replace($sourceDirectory, '', $sourceFilePath);
        $targetFilePath = $targetPath . $sourceRelativePath;

        $this->ensureDirectoryExists(dirname($targetFilePath));

        if(is_file($targetFilePath) && ! $this->input->getOption('overwrite'))
        {
            $this->output(sprintf('<info> - File</info> %s <info>already exists</info>', $sourceRelativePath), OutputInterface::VERBOSITY_VERY_VERBOSE);

            return ;
        }

        $this->output(sprintf('<info> - Overwriting file</info> %s', $sourceRelativePath), OutputInterface::VERBOSITY_VERY_VERBOSE);

        $this->copyFile($sourceFilePath, $targetFilePath);
    }

    private function output($message, $verbosity = OutputInterface::VERBOSITY_NORMAL)
    {
        if($verbosity <= $this->output->getVerbosity())
        {
            $this->output->writeln($message);
        }
    }

    private function copyFile($source, $target)
    {
        file_put_contents($target, file_get_contents($source));
    }

    private function enforceEndingSlash($path)
    {
        return rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
}
