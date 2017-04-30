<?php

class SassHandlerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var SassHandler
     */
    private $sassHandler;

    /**
     * Path to the directory with fixture files
     * @var string
     */
    private $fixturesDirectory;

    protected function setUp()
    {
        $this->sassHandler = new SassHandler;
        $this->sassHandler->compilerPath =
            __DIR__ . '/../vendor/leafo/scssphp/scss.inc.php';

        // Use "compressed" formatting to simplify code with assertions
        $this->sassHandler->compilerOutputFormatting = SassHandler::OUTPUT_FORMATTING_COMPRESSED;

        $this->fixturesDirectory = __DIR__ . '/fixtures/';

        // Cleanup
        $compiledDirectory = Yii::getPathOfAlias($this->sassHandler->sassCompiledPath);
        if (is_dir($compiledDirectory)) {
            CFileHelper::removeDirectory($compiledDirectory);
        }
    }

    /**
     * Test integration with scssphp compiler
     */
    public function testCompile()
    {
        $scssFile = $this->fixturesDirectory . 'compile.scss';
        $this->assertEquals(
            'body a{color:red}',
            $this->sassHandler->compile($scssFile)
        );
    }

    /**
     * Test integration with scssphp-compass library
     */
    public function testCompass()
    {
        $this->sassHandler->compassPath =
            __DIR__ . '/../vendor/leafo/scssphp-compass/compass.inc.php';
        $this->sassHandler->enableCompass = true;
        $scssFile = $this->fixturesDirectory . 'compass.scss';
        $this->assertEquals(
            'div{filter:progid:DXImageTransform.Microsoft.Alpha(Opacity=10);opacity:0.1}',
            $this->sassHandler->compile($scssFile)
        );
    }

    public function testGetCompiledFile()
    {
        $sourcePath = $this->fixturesDirectory . 'import.scss';
        $compiledPath = $this->sassHandler->getCompiledFile($sourcePath);
        $expectedPath = Yii::getPathOfAlias($this->sassHandler->sassCompiledPath)
            . DIRECTORY_SEPARATOR . 'import-'
            . substr(md5($sourcePath), -8)
            . '.css';

        $this->assertEquals($expectedPath, $compiledPath);
        $this->assertEquals(
            'body a{color:red}',
            file_get_contents($compiledPath)
        );
    }

    public function testGetCompiledFileWithoutRecompilation()
    {
        $sourcePath = $this->fixturesDirectory . 'import.scss';

        // First compilation request
        $this->sassHandler->getCompiledFile($sourcePath);

        // Second compilation request
        $compiledPath = $this->sassHandler->getCompiledFile($sourcePath);

        $expectedPath = Yii::getPathOfAlias($this->sassHandler->sassCompiledPath)
            . DIRECTORY_SEPARATOR . 'import-'
            . substr(md5($sourcePath), -8)
            . '.css';

        $this->assertEquals($expectedPath, $compiledPath);
        $this->assertEquals(
            'body a{color:red}',
            file_get_contents($compiledPath)
        );
    }
}
