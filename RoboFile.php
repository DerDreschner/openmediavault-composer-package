<?php
require_once("vendor/autoload.php");

use DerDreschner\OpenMediaVault\ComposerPackage\SchemeExtractor;
use Robo\Tasks;

const PHP_EXPORT_DIRECTORY = "./src/openmediavault";
const SCHEMES_EXPORT_DIRECTORY = "./src/schemas";
const OMV_CLONE_DIRECTORY = "./openmediavault";
const OMV_DATA_MODELS_DIRECTORY = OMV_CLONE_DIRECTORY . "/deb/openmediavault/usr/share/openmediavault/datamodels";
const OMV_DATA_MODELS_README_FILE = OMV_DATA_MODELS_DIRECTORY . "/README";
const OMV_MAKE_WORKBENCH_FILE = OMV_CLONE_DIRECTORY . "/deb/openmediavault/usr/sbin/omv-mkworkbench";
const OMV_PHP_DIRECTORY = OMV_CLONE_DIRECTORY . "/deb/openmediavault/usr/share/php/openmediavault";

class RoboFile extends Tasks
{
    /**
     * @command update-repository
     */
    public function ProcessOpenMediaVaultFiles(): void
    {
        $this->CheckoutOpenMediaVaultRepository();
        $this->CopyOpenMediaVaultPhpFiles();
        $this->GenerateOpenMediaVaultJsonSchemes();
        $this->UpdateComposerAutoloader();
    }

    /**
     * @command openmediavault:checkout-git-repository
     */
    public function CheckoutOpenMediaVaultRepository(): void
    {
        $this->_deleteDir(OMV_CLONE_DIRECTORY);

        $this->taskGitStack()
            ->cloneShallow("https://github.com/openmediavault/openmediavault", OMV_CLONE_DIRECTORY)
            ->run();
    }

    /**
     * @command openmediavault:copy-php-files
     */
    public function CopyOpenMediaVaultPhpFiles(): void
    {
        $this->_cleanDir(PHP_EXPORT_DIRECTORY);
        $this->_copyDir(OMV_PHP_DIRECTORY, PHP_EXPORT_DIRECTORY);
    }

    /**
     * @command openmediavault:generate-json-schemes
     */
    public function GenerateOpenMediaVaultJsonSchemes(): void
    {
        $this->_cleanDir(SCHEMES_EXPORT_DIRECTORY);

        $extractor = new SchemeExtractor();
        $extractor->ProcessPredefinedRpcFiles(OMV_DATA_MODELS_DIRECTORY, SCHEMES_EXPORT_DIRECTORY);
        $extractor->processMkWorkbenchFile(OMV_MAKE_WORKBENCH_FILE, SCHEMES_EXPORT_DIRECTORY);
        $extractor->extractAndExportConfigScheme(OMV_DATA_MODELS_README_FILE, SCHEMES_EXPORT_DIRECTORY);
        $extractor->extractAndExportGeneralRpcScheme(OMV_DATA_MODELS_README_FILE, SCHEMES_EXPORT_DIRECTORY);
    }

    public function UpdateComposerAutoloader(): void
    {
        $this->taskComposerDumpAutoload()
            ->run();
    }

    /**
     * @command run-tests
     */
    public function RunTests(): void
    {
        if(!is_dir(OMV_CLONE_DIRECTORY)) {
            $this->CheckoutOpenMediaVaultRepository();
        }

        $this->taskPhpUnit()
            ->file("./tests/*")
            ->run();
    }
}
