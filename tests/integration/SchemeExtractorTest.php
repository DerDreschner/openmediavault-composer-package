<?php
namespace DerDreschner\OpenMediaVault\ComposerPackage\Tests\IntegrationTests;
use DerDreschner\OpenMediaVault\ComposerPackage\SchemeExtractor;
use DerDreschner\OpenMediaVault\ComposerPackage\Tests\Fixtures\JsonTestsFixture;
use JetBrains\PhpStorm\ExpectedValues;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamContent;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use function PHPUnit\Framework\assertTrue;

require_once("RoboFile.php");

const JSON_SCHEMES_TO_TEST_AGAINST = [
    "http://json-schema.org/draft-06/schema#",
    "http://json-schema.org/draft-07/schema#",
    "https://json-schema.org/draft/2019-09/schema",
    "https://json-schema.org/draft/2020-12/schema"
];

final class SchemeExtractorTest extends JsonTestsFixture
{
    private SchemeExtractor $extractor;
    private vfsStreamDirectory $virtualDirectory;

    #[Before]
    public function prepareUniversallyUsedClasses(): void
    {
        $this->extractor = new SchemeExtractor();
        $this->virtualDirectory = vfsStream::setup();
    }

    #[Test]
    #[DataProvider('allJsonSchemeVersions')]
    public function extractedPredefinedRpcSchemesAreValid(#[ExpectedValues(JSON_SCHEMES_TO_TEST_AGAINST)] string $jsonSchemeVersion): void
    {
        $this->extractor->ProcessPredefinedRpcFiles(OMV_DATA_MODELS_DIRECTORY, $this->virtualDirectory->url());
        $this->validateJsonFilesInOutputDirectory($jsonSchemeVersion);
    }


    #[Test]
    #[DataProvider('allJsonSchemeVersions')]
    public function extractedUiSchemesAreValid(#[ExpectedValues(JSON_SCHEMES_TO_TEST_AGAINST)] string $jsonSchemeVersion): void
    {
        $this->extractor->processMkWorkbenchFile(OMV_MAKE_WORKBENCH_FILE, $this->virtualDirectory->url());
        $this->validateJsonFilesInOutputDirectory($jsonSchemeVersion);
    }

    #[Test]
    #[DataProvider('allJsonSchemeVersions')]
    public function extractedConfigSchemeIsValid(#[ExpectedValues(JSON_SCHEMES_TO_TEST_AGAINST)] string $jsonSchemeVersion): void
    {
        $this->extractor->extractAndExportConfigScheme(OMV_DATA_MODELS_README_FILE, $this->virtualDirectory->url());
        $this->validateJsonFilesInOutputDirectory($jsonSchemeVersion);
    }

    #[Test]
    #[DataProvider('allJsonSchemeVersions')]
    public function extractedGeneralRpcSchemeIsValid(#[ExpectedValues(JSON_SCHEMES_TO_TEST_AGAINST)] string $jsonSchemeVersion): void
    {
        $this->extractor->extractAndExportGeneralRpcScheme(OMV_DATA_MODELS_README_FILE, $this->virtualDirectory->url());
        $this->validateJsonFilesInOutputDirectory($jsonSchemeVersion);
    }

    public function validateJsonFilesInOutputDirectory(string $jsonSchemeVersion): void
    {
        foreach ($this->virtualDirectory->getIterator() as $file) {
            if ($file->getType() == vfsStreamContent::TYPE_FILE) {
                /** @noinspection PhpPossiblePolymorphicInvocationInspection */
                $fileContent = json_decode($file->getContent());
                $validationResult = $this->validator->validate($fileContent, $jsonSchemeVersion);

                assertTrue($validationResult->isValid());
            }
        }
    }

    public static function allJsonSchemeVersions(): array
    {
        $array = [];

        foreach (JSON_SCHEMES_TO_TEST_AGAINST as $jsonSchemeVersion) {
            $array["Test against schema $jsonSchemeVersion"] = [$jsonSchemeVersion];
        }

        return $array;
    }
}