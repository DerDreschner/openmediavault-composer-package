<?php

const DATAMODEL_DIRECTORY_PATH = "./src/datamodels";
const SCHEMES_DIRECTORY_PATH = "./src/schemas";
const MK_WORKBENCH_PATH = "./omv-mkworkbench";

use Ahc\Json\Comment;
use Robo\Tasks;

require_once("vendor/autoload.php");
require_once("SchemeExtractor.php");

class RoboFile extends Tasks
{
    public function GenerateJsonSchemes(): void
    {
        $this->CleanSchemasDirectory();

        $extractor = new SchemeExtractor();
        $extractor->ProcessRpcFiles();
        $extractor->ProcessMkWorkbenchFile();
    }

    public function CleanSchemasDirectory(): void
    {
        $this->_cleanDir(SCHEMES_DIRECTORY_PATH);
    }

    public function RunTests() {
        $this->CleanSchemasDirectory();

        $this->taskPhpUnit()
            ->file("./tests/RoboTest.php")
            ->run();
    }
}
