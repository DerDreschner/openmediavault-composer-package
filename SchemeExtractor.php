<?php


use Ahc\Json\Comment;

class SchemeExtractor
{
    public function sanitizeJson($string)
    {
        // Fix broken booleans
        $string = preg_replace("/True/", "true", $string);
        $string = preg_replace("/False/", "false", $string);

        // Fix single quoted strings
        return preg_replace("/'+/", "\"", $string);
    }

    public function ExtractUiScheme($regex, string $content)
    {
        $matches = [];
        preg_match($regex, $content, $matches);
        $sanitizedJson = $this->sanitizeJson($matches[1]);
        return Comment::parse($sanitizedJson, true);
    }

    public function ProcessMkWorkbenchFile(): void
    {
        $python_script = file_get_contents(MK_WORKBENCH_PATH);
        $this->extractAndExportUiScheme("component", $python_script);
        $this->extractAndExportUiScheme("widget", $python_script);
        $this->extractAndExportUiScheme("navigation", $python_script);
        $this->extractAndExportUiScheme("route", $python_script);
        $this->extractAndExportUiScheme("log", $python_script);
        $this->extractAndExportUiScheme("mkfs", $python_script);
    }

    public function extractAndExportUiScheme(string $type, string $mkworkbench_content): void
    {
        $regex = "/{$type}_schema = openmediavault.datamodel.Schema\(([^)]*)\)/";
        $scheme = $this->ExtractUiScheme($regex, $mkworkbench_content);

        $this->exportJsonScheme($type, $scheme);
    }

    public function exportJsonScheme(string $type, array $content): void
    {
        $this->sanitizeJsonSchema($content);

        $filename = $type . ".json";
        $attributes = json_encode($content);

        $filepath = SCHEMES_DIRECTORY_PATH . DIRECTORY_SEPARATOR . $filename;

        file_put_contents($filepath, $attributes);
    }

    public function ProcessRpcFiles(): void
    {
        $isRpcFile = function ($callback): bool {
            return !is_dir($callback) && str_contains($callback, "rpc.");
        };

        $exportSchemes = function ($filename): void {
            $this->extractAndExportRpcSchemes($filename);
        };

        $dataModelDirectory = collect(scandir(DATAMODEL_DIRECTORY_PATH));

        $dataModelDirectory
            ->filter($isRpcFile)
            ->each($exportSchemes);
    }

    public function extractAndExportRpcSchemes(string $filename): void
    {
        $filepath = DATAMODEL_DIRECTORY_PATH . DIRECTORY_SEPARATOR . $filename;
        $content = collect(Comment::parse(file_get_contents($filepath), true));

        $isSingleSchemeFile = ($content->keys()->contains("params") || $content->keys()->contains("properties"));

        $exportScheme = function ($content, $filename): void {
            $schemeType = $content["id"] ? $content["id"] :  strtok($filename, "json");
            $contentTree = $content["params"] ? $content["params"] : $content["properties"];
            $this->exportJsonScheme($schemeType, $contentTree);
        };

        $isSingleSchemeFile ? $exportScheme($content->toArray(), $filename) : $content->each($exportScheme, $filename);

    }

    public function sanitizeJsonSchema(array &$item, SplStack $callstack = new SplStack()): void
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
                    $this->sanitizeJsonSchema($value, $callstack);
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

                if ($key == "required") {
                    $callstack->rewind();
                    $requiredElement = $callstack->current()["key"];

                    $elementToAppendFound = false;

                    while (!$elementToAppendFound) {
                        $callstack->next();

                        $potentialKey = $callstack->current()["key"];
                        $potentialParent = &$callstack->current()["parent"];

                        if ($potentialKey == "properties") {
                            if (array_key_exists($key, $potentialParent) && is_bool($potentialParent[$key])) {
                                unset($potentialParent[$key]);
                            }

                            $potentialParent[$key][] = $requiredElement;
                            unset($item[$key]);

                            $elementToAppendFound = true;
                        }
                    }
                }
            }
        }
    }
}