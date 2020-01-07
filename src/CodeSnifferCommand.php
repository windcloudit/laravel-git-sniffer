<?php

namespace WindCloud\LaravelGitSniffer;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;


/**
 * Class CodeSnifferCommand
 * @package WindCloud\LaravelGitSniffer
 */
class CodeSnifferCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'git-sniffer:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check code standards on PHP_CodeSniffer and ESLint';

    protected $config;

    /** @var Filesystem */
    protected $files;

    /**
     * CodeSnifferCommand constructor.
     * @param $config
     * @param Filesystem $files
     */
    public function __construct($config, Filesystem $files)
    {
        parent::__construct();
        $this->config = $config;
        $this->files = $files;
    }

    /**
     * Handle the command
     */
    public function handle()
    {
        $environment = $this->config->get('app.env');
        $gitSnifferEnv = $this->config->get('git-sniffer.env');

        if ($environment !== $gitSnifferEnv) {
            return;
        }

        $phpcsBin = $this->config->get('git-sniffer.phpcs_bin');
        $phpcbfBin = $this->config->get('git-sniffer.phpcbf_bin');
        $eslintBin = $this->config->get('git-sniffer.eslint_bin');
        $eslintConfig = $this->config->get('git-sniffer.eslint_config');
        $eslintIgnorePath = $this->config->get('git-sniffer.eslint_ignore_path');

        if (!empty($phpcsBin)) {
            if (!$this->files->exists($phpcsBin)) {
                $this->error('PHP CodeSniffer bin not found');
                exit(1);
            }
        }

        if (!empty($eslintBin)) {
            if (!$this->files->exists($eslintBin)) {
                $this->error('ESLint bin not found');
                exit(1);
            } elseif (!$this->files->exists($eslintConfig)) {
                $this->error('ESLint config file not found');
                exit(1);
            }

            if (!empty($eslintIgnorePath)) {
                if (!$this->files->exists($eslintIgnorePath)) {
                    $this->error('ESLint ignore file not found');
                    exit(1);
                }
            }
        }

        if (empty($phpcsBin) && empty($eslintBin)) {
            $this->error('Eslint bin and Phpcs bin are not configured');
            exit(1);
        }

        $revision = trim(shell_exec('git rev-parse --verify HEAD'));
        $against = "4b825dc642cb6eb9a060e54bf8d69288fbee4904";
        if (!empty($revision)) {
            $against = 'HEAD';
        }

        //this is the magic:
        //retrieve all files in staging area that are added, modified or renamed
        //but no deletions etc
        $files = trim(shell_exec("git diff-index --name-only --cached --diff-filter=ACMR {$against} --"));
        if (empty($files)) {
            exit(0);
        }

        //$tempStaging = $this->config->get('git-sniffer.temp');
        //create temporary copy of staging area
        //if ($this->files->exists($tempStaging)) {
        //    $this->files->deleteDirectory($tempStaging);
        //}

        $fileList = explode("\n", $files);
        $validPhpExtensions = $this->config->get('git-sniffer.phpcs_extensions');
        $validEslintExtensions = $this->config->get('git-sniffer.eslint_extensions');
        $validFiles = [];

        foreach ($fileList as $l) {
            if (!empty($phpcsBin)) {
                if (in_array($this->files->extension($l), $validPhpExtensions)) {
                    $validFiles[] = $l;
                }
            }

            if (!empty($eslintBin)) {
                if (in_array($this->files->extension($l), $validEslintExtensions)) {
                    $validFiles[] = $l;
                }
            }
        }

        //Copy contents of staged version of files to temporary staging area
        //because we only want the staged version that will be commited and not
        //the version in the working directory
        if (empty($validFiles)) {
            exit(0);
        }

        //$this->files->makeDirectory($tempStaging);
        $phpStaged = [];
        $eslintStaged = [];
        foreach ($validFiles as $f) {

            if (!empty($phpcsBin)) {
                if (in_array($this->files->extension($f), $validPhpExtensions)) {
                    $phpStaged[] = '"' . $f . '"';
                }
            }

            if (!empty($eslintBin)) {
                if (in_array($this->files->extension($f), $validEslintExtensions)) {
                    $eslintStaged[] = '"' . $f . '"';
                }
            }
        }

        $eslintOutput = null;
        $phpcsOutput = null;

        if (!empty($phpcsBin) && !empty($phpStaged)) {
            $standard = $this->config->get('git-sniffer.standard');
            $encoding = $this->config->get('git-sniffer.encoding');
            $ignoreFiles = $this->config->get('git-sniffer.phpcs_ignore');
            $phpcsExtensions = implode(',', $validPhpExtensions);
            $sniffFiles = implode(' ', $phpStaged);
            $phpcsIgnore = null;
            if (!empty($ignoreFiles)) {
                $phpcsIgnore = ' --ignore=' . implode(',', $ignoreFiles);
            }

            $phpcbfOutput = shell_exec("\"{$phpcbfBin}\" -p {$sniffFiles}");
            // Add all file after modify again
            shell_exec("git add {$sniffFiles}");
            echo $phpcbfOutput;
            echo "\n";
            $phpcsOutput = shell_exec("\"{$phpcsBin}\" -s --standard={$standard} --encoding={$encoding} --extensions={$phpcsExtensions}{$phpcsIgnore} {$sniffFiles}");
        }

        if (!empty($eslintBin) && !empty($eslintStaged)) {
            $eslintFiles = implode(' ', $eslintStaged);
            $eslintIgnore = ' --no-ignore';

            if (!empty($eslintIgnorePath)) {
                $eslintIgnore = ' --ignore-path "' . $eslintIgnorePath . '"';
            }

            $eslintOutput = shell_exec("\"{$eslintBin}\" -c \"{$eslintConfig}\"{$eslintIgnore} --quiet  {$eslintFiles}");
        }

        //$this->files->deleteDirectory($tempStaging);


        if (!empty($phpcsOutput) || !empty($eslintOutput)) {
            if (!empty($phpcsOutput)) {
                echo $phpcsOutput;
            }

            if (!empty($eslintOutput)) {
                $this->error($eslintOutput);
            }

            exit(1);
        }

        $isRunPhpUnit = $this->config->get('git-sniffer.is_run_phpunit');
        //Run test case
        if($isRunPhpUnit && $this->runUnitTest() === false) {
            exit(1);
        }

        exit(0); // Commit code
    }

    /**
     * Function use for run unit test
     * @author: tat.pham
     */
    private function runUnitTest()
    {
        $phpunitBin = $this->config->get('git-sniffer.phpunit_bin');

        echo PHP_EOL;
        // output a little introduction
        echo '>> Starting unit tests' . PHP_EOL;
        echo "\n";
        // get the name for this project; probably the topmost folder name
        $projectName = basename(getcwd());
        // execute unit tests (it is assumed that a phpunit.xml configuration is present
        // in the root of the project)
        $process = $this->process("\"{$phpunitBin}\"");
        $exitCode = $process->getExitCode();
        echo PHP_EOL;
        // if the build failed, output a summary and fail
        if ($exitCode !== 0) {
            return false;
        }
        echo PHP_EOL;
        echo '>> All tests for ' . $projectName . ' passed.' . PHP_EOL;
        echo PHP_EOL;
        return true;

    }

    /**
     * @param $command
     * @return Process
     */
    private function process($command)
    {
        $process = new Process($command);
        $process->setTimeout(0);
        $process->run(function ($type, $line) {
            $this->output->write($line);
        });
        return $process;
    }

}
