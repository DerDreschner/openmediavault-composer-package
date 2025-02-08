<?php
namespace DerDreschner\OpenMediaVault\ComposerPackage;

use Ahc\Json\Comment;
use JetBrains\PhpStorm\Language;
use SplStack;

class SchemeExtractor
{
    public function sanitizeJson(#[Language("JSON")] string $jsonToSanitize) : string
    {
        // Fix broken booleans
        $jsonToSanitize = preg_replace("/True/", "true", $jsonToSanitize);
        $jsonToSanitize = preg_replace("/False/", "false", $jsonToSanitize);

        // Fix single quoted strings
        return preg_replace("/'+/", "\"", $jsonToSanitize);
    }

    public function extractUiScheme(#[Language("RegExp")] string $regex, #[Language("JSON")] string $content)
    {
        $matches = [];
        preg_match($regex, $content, $matches);
        $sanitizedJson = $this->sanitizeJson($matches[1]);
        return Comment::parse($sanitizedJson, true);
    }

    public function processMkWorkbenchFile(string $mkWorkbenchFilePath, string $exportDirectoryPath): void
    {
        $python_script = file_get_contents($mkWorkbenchFilePath);
        $this->extractAndExportUiScheme("component", $python_script, $exportDirectoryPath);
        $this->extractAndExportUiScheme("widget", $python_script, $exportDirectoryPath);
        $this->extractAndExportUiScheme("navigation", $python_script, $exportDirectoryPath);
        $this->extractAndExportUiScheme("route", $python_script, $exportDirectoryPath);
        $this->extractAndExportUiScheme("log", $python_script, $exportDirectoryPath);
        $this->extractAndExportUiScheme("mkfs", $python_script, $exportDirectoryPath);
    }

    public function extractAndExportUiScheme(string $type, #[Language("Python")] string $mkWorkbenchContent, string $outputDirectory): void
    {
        print("Extracting and exporting UI schemes for $type" . PHP_EOL);
        $regex = "/{$type}_schema = openmediavault.datamodel.Schema\(([^)]*)\)/";
        $scheme = $this->extractUiScheme($regex, $mkWorkbenchContent);

        $this->exportJsonScheme($type, $scheme, $outputDirectory);
    }

    public function extractAndExportConfigScheme(string $filePath, string $outputDirectory): void
    {
        print("Extracting and exporting config scheme" . PHP_EOL);
        $file = file_get_contents($filePath);

        $schemeStart = "Configuration object\n================================================================================\n\nSchema\n======\n";
        $startOfJson = substr($file, strpos($file, $schemeStart) + strlen($schemeStart));
        $json = json_decode(substr($startOfJson, 0, strpos($startOfJson, "\n\n")), true);

        $this->exportJsonScheme("config", $json, $outputDirectory);
    }

    public function extractAndExportGeneralRpcScheme(string $filePath, string $outputDirectory): void
    {
        print("Extracting and exporting general RPC scheme" . PHP_EOL);
        $file = file_get_contents($filePath);

        $schemeStart = "RPC\n================================================================================\n\nSchema\n======\n";
        $startOfJson = substr($file, strpos($file, $schemeStart) + strlen($schemeStart));
        $json = json_decode(substr($startOfJson, 0, strpos($startOfJson, "\n\n")), true);

        $this->exportJsonScheme("rpc", $json, $outputDirectory);
    }

    public function exportJsonScheme(string $type, array $content, string $outputDirectory): void
    {
        $this->convertDraft3JsonSchema($content);

        $filename = "$type.json";
        $attributes = json_encode($content, JSON_PRETTY_PRINT);

        $filepath = $outputDirectory . DIRECTORY_SEPARATOR . $filename;

        file_put_contents($filepath, $attributes);
    }

    public function ProcessPredefinedRpcFiles(string $dataModelDirectoryPath, string $exportDirectoryPath): void
    {
        $isRpcFile = function ($callback): bool {
            return !is_dir($callback) && preg_match("/^rpc\..+\.json$/", $callback);
        };

        $exportSchemes = function ($filename) use ($dataModelDirectoryPath, $exportDirectoryPath): void {
            $this->extractAndExportPredefinedRpcSchemes($dataModelDirectoryPath . DIRECTORY_SEPARATOR . $filename, $exportDirectoryPath);
        };

        $rpcSchemes = collect(scandir($dataModelDirectoryPath));

        $rpcSchemes
            ->filter($isRpcFile)
            ->each($exportSchemes);
    }


    public function extractAndExportPredefinedRpcSchemes(string $filePath, string $outputDirectory): void
    {
        print("Extracting and exporting RPC schemes from $filePath" . PHP_EOL);
        $content = collect(Comment::parseFromFile($filePath, true));

        $isSingleSchemeFile = ($content->keys()->contains("params") || $content->keys()->contains("properties"));

        $exportScheme = function ($content) use ($outputDirectory, $filePath): void {
            $schemeType = $content["id"] ?:  basename($filePath, ".json");
            $contentTree = $content["params"] ?: $content["properties"];
            $this->exportJsonScheme($schemeType, $contentTree, $outputDirectory);
        };

        $isSingleSchemeFile
            ? $exportScheme($content->toArray())
            : $content->each($exportScheme);
    }

    public function convertDraft3JsonSchema(array &$item, SplStack $callstack = new SplStack()): void
    {
        if($callstack->count() === 0 && !array_key_exists("type", $item)) {
            $transformedItem["type"] = "object";
            $transformedItem["properties"] = array_key_exists(0, $item) ? $item[0]["properties"] : $item;
            $item = $transformedItem;
        }

        foreach ($item as $key => &$value) {
            if (is_array($value) && $key !== "enum") {
                if (count($value) == 0) {
                    unset($item[$key]);
                } else {
                    $callstack->push(["key" => $key, "parent" => &$item]);
                    $this->convertDraft3JsonSchema($value, $callstack);
                    $callstack->pop();
                }
            } else {
                if ($key == "type" && $value == "any") {
                    $value = [
                        "array",
                        "boolean",
                        "integer",
                        "null",
                        "number",
                        "object",
                        "string"
                    ];
                }

                if ($key == "required" || $key == "required_overwritten") {
                    $callstack->rewind();
                    $requiredElement = $callstack->current()["key"];

                    $elementToAppendFound = false;

                    while (!$elementToAppendFound) {
                        $callstack->next();

                        $potentialKey = $callstack->current()["key"];
                        $potentialParent = &$callstack->current()["parent"];

                        if ($potentialKey == "properties") {
                            if (array_key_exists($key, $potentialParent) && is_bool($potentialParent[$key])) {
                                $potentialParent["required_overwritten"] = true;
                                unset($potentialParent[$key]);
                            }

                            $potentialParent["required"][] = $requiredElement;
                            unset($item[$key]);

                            $elementToAppendFound = true;
                        }
                    }
                }
            }
        }
    }
}