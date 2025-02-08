<?php
namespace DerDreschner\OpenMediaVault\ComposerPackage\Tests\Fixtures;
use FilesystemIterator;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class JsonTestsFixture extends TestCase
{
    protected Validator $validator;

    #[Before]
    public function setErrorLevel(): void
    {
        error_reporting(E_ALL);
    }

    #[Before]
    public function hideOutputToConsoleBefore(): void
    {
        ob_start();
    }

    #[After]
    public function hideOutputToConsoleAfter(): void
    {
        ob_end_clean();
    }

    #[Before]
    public function registerAvailableJsonSchemes(): void
    {
        $this->validator = new Validator();
        $jsonSchemeResolver = $this->validator->resolver();

        $fileIterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                "./tests/fixture",
                FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($fileIterator as $file) {
            if (!$file->isDir() && $file->getExtension() == '') {
                $fullPath = $file->getPathname();
                $content = json_decode(file_get_contents($fullPath), true);
                $id = $content['$id'];
                $jsonSchemeResolver->registerFile($id, $fullPath);
            }
        }
    }
}