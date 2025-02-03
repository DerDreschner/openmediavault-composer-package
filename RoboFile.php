<?php

const DATAMODEL_DIRECTORY_PATH = "./src/datamodels";
const SCHEMES_DIRECTORY_PATH = "./src/schemas";
const MK_WORKBENCH_PATH = "./omv-mkworkbench";

use Robo\Tasks;

require_once("vendor/autoload.php");

class RoboFile extends Tasks
{
    public function GenerateJsonSchemes(): void
    {
        $this->CleanSchemasDirectory();
        $this->GenerateRpcSchemes();
        $this->GenerateUiSchemes();
    }

    private function CleanSchemasDirectory(): void
    {
        $this->_cleanDir(SCHEMES_DIRECTORY_PATH);
    }

    private function GenerateRpcSchemes(): void
    {
        $isRpcFile = function ($callback): bool {
            return !is_dir($callback) && str_contains($callback, "rpc.");
        };

        $exportSchemes = function ($filename): void {
            print_r("Exporting schemes from $filename..." . PHP_EOL);

            $filepath = DATAMODEL_DIRECTORY_PATH . DIRECTORY_SEPARATOR . $filename;
            $content = collect(json_decode(file_get_contents($filepath), true));

            $isSingleSchemeFile = $content->keys()->contains("params");

            $exportScheme = function ($content): void {
                $filename = $content["id"] . ".json";
                $filepath = SCHEMES_DIRECTORY_PATH . DIRECTORY_SEPARATOR . $filename;

                $attributes = json_encode($content["params"]);

                file_put_contents($filepath, $attributes);
            };

            $isSingleSchemeFile ? $exportScheme($content) : $content->each($exportScheme);;
        };

        $dataModelDirectory = collect(scandir(DATAMODEL_DIRECTORY_PATH));

        $dataModelDirectory
            ->filter($isRpcFile)
            ->each($exportSchemes);
    }

    private function GenerateUiSchemes(): void
    {
        $extractScheme = function ($type, $python_script) {
            print_r("Exporting schemes for user interface type {$type}..." . PHP_EOL);

            $regex = "/{$type}_schema = openmediavault.datamodel.Schema\(([^)]*)\)/";
            $scheme = $this->ExtractUiScheme($regex, $python_script);

            $filename = $type . ".json";
            $filepath = SCHEMES_DIRECTORY_PATH . DIRECTORY_SEPARATOR . $filename;

            $attributes = json_encode($scheme);
            file_put_contents($filepath, $attributes);
        };

        $python_script = file_get_contents(MK_WORKBENCH_PATH);
        $extractScheme("widget", $python_script);
        $extractScheme("navigation", $python_script);
        $extractScheme("route", $python_script);
        $extractScheme("log", $python_script);
        $extractScheme("mkfs", $python_script);
    }

    private function ExtractUiScheme($regex, string $content)
    {
        $sanitizeJson = function ($string) {
            // Fix broken booleans
            $string = preg_replace("/True/", "true", $string);
            $string = preg_replace("/False/", "false", $string);

            // Fix single quoted strings
            $string = preg_replace("/'+/", "\"", $string);

            // Remove newline control characters
            return str_replace(PHP_EOL, "", $string);
        };

        $matches = [];
        preg_match($regex, $content, $matches);
        $sanitizedJson = $sanitizeJson($matches[1]);

        return json_decode($sanitizedJson, true)["properties"];
    }
}
