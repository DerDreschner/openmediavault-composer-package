<?php
include_once('vendor/autoload.php');
require_once("SchemeExtractor.php");

use JsonSchema\Constraints\Constraint;
use Opis\JsonSchema\SchemaLoader;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

const DATAMODEL_DIRECTORY_PATH = "./src/datamodels";
const SCHEMES_DIRECTORY_PATH = "./src/schemas";
const MK_WORKBENCH_PATH = "./omv-mkworkbench";

const JSON_SCHEMES_DIRECTORY_PATH = "./tests/files/json-schemas";

const JSON_SCHEMES_TO_TEST = [
    "http://json-schema.org/draft-06/schema#",
    "http://json-schema.org/draft-07/schema#",
    "https://json-schema.org/draft/2019-09/schema",
    "https://json-schema.org/draft/2020-12/schema"
];

final class RoboTest extends TestCase
{
    private SchemeExtractor $extractor;
    private Validator $validator;

    private $jsonSchemeResolver;

    #[Before]
    public function prepareUniversallyUsedClasses(): void
    {
        $this->extractor = new SchemeExtractor();
        $this->validator = new Validator();
        $this->jsonSchemeResolver = $this->validator->resolver();

        $fileIterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(JSON_SCHEMES_DIRECTORY_PATH, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($fileIterator as $file) {
            if (!$file->isDir()) {
                $fullPath = $file->getPathname();
                $content = json_decode(file_get_contents($fullPath), true);
                $id = $content['$id'];
                $this->jsonSchemeResolver->registerFile($id, $fullPath);
            }
        }
    }

    #[Test]
    #[DataProvider('jsonStringsWithMalformedJson')]
    public function jsonSanitizer(string $stringToTest): void
    {
        $this->assertJson($this->extractor->sanitizeJson($stringToTest));
    }

    public static function jsonStringsWithMalformedJson(): array
    {
        return [
            'JSON with single quoted attributes' => ["{
                'type': 'rpc',
                'id': 'rpc.perfstats.set',
                'params': {
                    'type': 'object',
                    'properties': {
                        'enable': {
                            'type': 'boolean',
                            'required': true
                        }
                    }
                }
            }"],
            "JSON with capitalized booleans" => ['{
                "type": "rpc",
                "id": "rpc.perfstats.set",
                "params": {
                    "type": "object",
                    "properties": {
                        "enable": {
                            "type": "boolean",
                            "required": True
                        }
                    }
                }
            }']
        ];
    }

    #[Test]
    #[DataProvider('corruptedSchemes')]
    public function fixMalformedScheme(array $jsonArray, string $jsonSchemeVersion)
    {
        $this->extractor->sanitizeJsonSchema($jsonArray);

        $result = $this->validator->validate(json_decode(json_encode($jsonArray)), $jsonSchemeVersion);
        $this->assertTrue($result->isValid());
    }

    public static function corruptedSchemes(): array
    {
        $array = [];

        $jsonArray = ["JSON without top-level type attribute" => [json_decode('{
  "properties": {
    "enable": {
      "type": "boolean",
      "required": true
    }
  }
}', true)], "JSON with schema v3 required attribute" =>
            [json_decode('{
  "type": "object",
  "properties": {
    "enable": {
      "type": "boolean",
      "required": true
    }
  }
}', true)],
            "JSON with schema v3 any type" => [json_decode('{
  "type": "object",
  "properties": {
    "devicetype": {
      "type": "string",
      "enum": [ "desktop", "mobile" ],
      "required": true
    },
    "key": {
      "type": "string",
      "required": true
    },
    "value": {
      "type": "any",
      "required": true
    }
  }
}', true)],
            "JSON with empty properties attribute" => [json_decode('{
  "type": "object",
  "properties": {
    "data": {
      "type": "object",
      "properties": {
        "name": {
          "type": "string",
          "required": true
        },
        "type": {
          "type": "string",
          "enum": [
            "blankPage",
            "navigationPage",
            "formPage",
            "selectionListPage",
            "textPage",
            "tabsPage",
            "datatablePage",
            "rrdPage",
            "codeEditorPage"
          ],
          "required": true
        },
        "extends": {
          "type": "string"
        },
        "config": {
          "type": "object",
          "properties": []
        }
      }
    }
  }
}', true)]];

        foreach ($jsonArray as $type => $jsonSchema) {
            foreach (JSON_SCHEMES_TO_TEST as $jsonSchemeVersion) {
                $array["Test case '{$type}' against schema {$jsonSchemeVersion}"] = [$jsonSchema, $jsonSchemeVersion];
            }
        }

        return $array;
    }

    public static function rpcSchemes(): array
    {
        $array = [];

        $schemaFiles = scandir(DATAMODEL_DIRECTORY_PATH);
        foreach ($schemaFiles as $schemaFile) {
            foreach (JSON_SCHEMES_TO_TEST as $jsonSchemeVersion) {
                if (str_starts_with($schemaFile, "rpc.") && str_ends_with($schemaFile, ".json")) {
                    $array["Test rpc types {$schemaFile} against schema {$jsonSchemeVersion}"] = [$schemaFile, $jsonSchemeVersion];
                }
            }
        }

        return $array;
    }

    #[Test]
    #[DataProvider('rpcSchemes')]
    public function areExtractedRpcSchemesValid(string $filename, string $jsonSchemeVersion): void
    {
        error_reporting(E_ALL);
        $this->extractor->extractAndExportRpcSchemes($filename);

        $files = scandir(SCHEMES_DIRECTORY_PATH . DIRECTORY_SEPARATOR);
        $prefix = strtok($filename, "json");
        foreach ($files as $file) {
            if (str_starts_with($file, $prefix) && str_ends_with($file, ".json")) {
                $fileToValidate = SCHEMES_DIRECTORY_PATH . DIRECTORY_SEPARATOR . $file;
                $this->assertFileExists($fileToValidate);

                $fileContent = json_decode(file_get_contents($fileToValidate));
                $result = $this->validator->validate($fileContent, $jsonSchemeVersion);
                $this->assertTrue($result->isValid());
            }
        }
    }

    #[Test]
    #[DataProvider('uiSchemes')]
    public function areExtractedUiSchemesValid(string $schemeType, string $jsonSchemeVersion): void
    {
        $pythonString = file_get_contents(MK_WORKBENCH_PATH);

        $this->extractor->extractAndExportUiScheme($schemeType, $pythonString);

        $fileToValidate = SCHEMES_DIRECTORY_PATH . DIRECTORY_SEPARATOR . $schemeType . ".json";
        $this->assertFileExists($fileToValidate);

        $fileContent = json_decode(file_get_contents($fileToValidate));
        $result = $this->validator->validate($fileContent, $jsonSchemeVersion);
        $this->assertTrue($result->isValid());
    }

    public static function uiSchemes(): array
    {
        $array = [];

        $uiSchemes = ["component", "widget", "navigation", "route", "log", "mkfs"];
        foreach ($uiSchemes as $type) {
            foreach (JSON_SCHEMES_TO_TEST as $jsonSchemeVersion) {
                $array["Test UI type '{$type}' against schema {$jsonSchemeVersion}"] = [$type, $jsonSchemeVersion];
            }
        }

        return $array;
    }
}
