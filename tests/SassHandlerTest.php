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
    }

    /**
     * Test integration with scssphp compiler
     */
    public function testCompile()
    {
        $scssFile = $this->fixturesDirectory . 'compile.scss';
        $this->assertEquals(
            'body a{color:red;}',
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
            'div{filter:progid:DXImageTransform.Microsoft.Alpha(Opacity=10);opacity:0.1;}',
            $this->sassHandler->compile($scssFile)
        );
    }
}
