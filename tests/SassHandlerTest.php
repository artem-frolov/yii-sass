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
}
