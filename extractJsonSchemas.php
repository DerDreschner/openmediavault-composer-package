<?php
include_once 'vendor/autoload.php';

const DATAMODEL_DIRECTORY_PATH = "./src/datamodels";
const SCHEMES_DIRECTORY_PATH = "./src/schemas";

$isFile = function ($file) {
    return !is_dir($file);
};

$deleteFile = function ($filename) {
    unlink(SCHEMES_DIRECTORY_PATH . DIRECTORY_SEPARATOR . $filename);
};

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

$schemesDirectory = collect(scandir(SCHEMES_DIRECTORY_PATH));

$schemesDirectory
    ->filter($isFile)
    ->each($deleteFile);

$dataModelDirectory = collect(scandir(DATAMODEL_DIRECTORY_PATH));

$dataModelDirectory
    ->filter($isRpcFile)
    ->each($exportSchemes);