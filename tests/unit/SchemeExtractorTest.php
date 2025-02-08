<?php
namespace DerDreschner\OpenMediaVault\ComposerPackage\Tests\UnitTests;
use DerDreschner\OpenMediaVault\ComposerPackage\SchemeExtractor;
use DerDreschner\OpenMediaVault\ComposerPackage\Tests\Fixtures\JsonTestsFixture;
use JetBrains\PhpStorm\ExpectedValues;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use function PHPUnit\Framework\assertJson;
use function PHPUnit\Framework\assertTrue;

const JSON_SCHEMES_TO_TEST_AGAINST = [
    "http://json-schema.org/draft-06/schema#",
    "http://json-schema.org/draft-07/schema#",
    "https://json-schema.org/draft/2019-09/schema",
    "https://json-schema.org/draft/2020-12/schema"
];

final class SchemeExtractorTest extends JsonTestsFixture
{
    private SchemeExtractor $extractor;

    #[Before]
    public function prepareUniversallyUsedClasses(): void
    {
        $this->extractor = new SchemeExtractor();
    }

    #[Test]
    #[DataProvider('jsonStringsWithMalformedJson')]
    public function sanitizeJsonTest(string $stringUnderTest): void
    {
        $sanitizedJson = $this->extractor->sanitizeJson($stringUnderTest);
        assertJson($sanitizedJson);
    }

    #[Test]
    #[DataProvider('corruptedSchemes')]
    public function convertDraft3JsonSchemaTest(array $jsonArray, #[ExpectedValues(JSON_SCHEMES_TO_TEST_AGAINST)] string $jsonSchemeVersion)
    {
        $this->extractor->convertDraft3JsonSchema($jsonArray);

        $validationResult = $this->validator->validate(json_decode(json_encode($jsonArray)), $jsonSchemeVersion);
        assertTrue($validationResult->isValid());
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
}', true)], "JSON with required attribute" =>
            [json_decode('{
  "type": "object",
  "properties": {
    "enable": {
      "type": "boolean",
      "required": true
    }
  }
}', true)],
            "JSON with required attribute in multiple properties" => [json_decode('{
    "type": "object",
    "properties": {
        "version": {"type": "string", "required": true},
        "type": {"type": "string", "enum": ["component"], "required": true},
        "data": {"type": "object", "properties": {
            "name": {"type": "string", "required": true},
            "type": {"type": "string", "enum": [
                "blankPage",
                "navigationPage",
                "formPage",
                "selectionListPage",
                "textPage",
                "tabsPage",
                "datatablePage",
                "rrdPage",
                "codeEditorPage"
            ], "required": true},
            "extends": {"type": "string"},
            "config": {"type": "object", "properties": {}}
        }, "required": true}
    }
}', true)],
            "JSON with attribute type: any" => [json_decode('{
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
}', true)]];

        foreach ($jsonArray as $type => $jsonSchema) {
            foreach (JSON_SCHEMES_TO_TEST_AGAINST as $jsonSchemeVersion) {
                $array["Test case '$type' against schema $jsonSchemeVersion"] = [$jsonSchema, $jsonSchemeVersion];
            }
        }

        return $array;
    }
}