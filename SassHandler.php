<?php

use Padaliyajay\PHPAutoprefixer\Autoprefixer;

/**
 * Sass Handler
 *
 * Compiles SCSS file(s) on-the-fly
 * and publishes and/or registers output .css file
 *
 * @property ExtendedScssc $compiler
 *
 * @author Artem Frolov <artem@frolov.net>
 * @link https://github.com/artem-frolov/yii-sass
 */
class SassHandler extends CApplicationComponent
{
    /**
     * Path for cache files. Will be used if Yii caching is not enabled.
     * Will be chmod'ed to become writable, see "writableDirectoryPermissions"
     * parameter.
     * Yii aliases can be used.
     *
     * Defaults to 'application.runtime.sass-cache'
     *
     * @var string
     */
    public $cachePath = 'application.runtime.sass-cache';

    /**
     * Path and filename of scss.inc.php
     *
     * Defaults to the relative location in Composer's vendor directory:
     * __DIR__ . "/../../scssphp/scssphp/scss.inc.php"
     *
     * @var string
     */
    public $compilerPath;

    /**
     * Path to a directory with compiled CSS files.
     * Will be created automatically if it doesn't exist.
     * Will be chmod'ed to become writable, see "writableDirectoryPermissions"
     * parameter.
     * Yii aliases can be used.
     *
     * Defaults to 'application.runtime.sass-compiled'
     *
     * @var string
     */
    public $sassCompiledPath = 'application.runtime.sass-compiled';

    /**
     * Force compilation/recompilation on each request.
     *
     * False value means that compilation will be done only if
     * source SCSS file or related imported files have been
     * changed after previous compilation.
     *
     * Defaults to false
     *
     * @var boolean
     */
    public $forceCompilation = false;

    /**
     * Turn on/off overwriting of already compiled CSS files.
     * Will be ignored if "forceCompilation" parameter's value is true.
     *
     * True value means that compiled CSS file will be overwritten
     * if the source SCSS file or related imported files have
     * been changed after previous compilation.
     *
     * False value means that compilation will be done only if
     * an output CSS file doesn't exist.
     *
     * Defaults to true
     *
     * @var boolean
     */
    public $allowOverwrite = true;

    /**
     * Automatically add directory containing SCSS file being processed
     * as an import path for the @import Sass directive.
     *
     * Defaults to true
     *
     * @var boolean
     */
    public $autoAddCurrentDirectoryAsImportPath = true;

    /**
     * List of import paths.
     * Can be a list of strings or callable functions:
     * function($searchPath) {return $targetPath;}
     *
     * Defaults to an empty array
     *
     * @var string[]|callable[]
     */
    public $importPaths = array();

    /**
     * Chmod permissions used for creating/updating of writable
     * directories for cache files and compiled CSS files.
     * Mind the leading zero for octal values.
     *
     * Defaults to 0777
     *
     * @var integer
     */
    public $writableDirectoryPermissions = 0777;

    /**
     * Chmod permissions used for creating/updating of writable
     * cache files and compiled CSS files.
     * Mind the leading zero for octal values.
     *
     * Defaults to 0666
     *
     * @var integer
     */
    public $writableFilePermissions = 0776;

    /**
     * Default value for $hashByName parameter in extension's methods.
     * $hashByName value determines whether the published file should be named
     * as the hashed basename. If false, the name will be the hash taken
     * from dirname of the path being published and path mtime.
     * Set to true if the path being published is shared
     * among different extensions.
     *
     * Defaults to false
     *
     * @see CAssetManager::publish()
     * @var bool
     */
    public $defaultHashByName = false;

    /**
     * Customize the formatting of the output CSS.
     * Use one of the SassHandler::OUTPUT_FORMATTING_* constants
     * to set the formatting type.
     * @link http://leafo.net/scssphp/docs/#output_formatting
     *
     * Default is OUTPUT_FORMATTING_NESTED
     *
     * @var string
     */
    public $compilerOutputFormatting = self::OUTPUT_FORMATTING_NESTED;

    /**
     * Id of the cache application component.
     * Defaults to 'cache' (the primary cache application component)
     *
     * @var string
     */
    public $cacheComponentId = 'cache';

    const OUTPUT_FORMATTING_NESTED     = 'nested';
    const OUTPUT_FORMATTING_COMPRESSED = 'compressed';
    const OUTPUT_FORMATTING_CRUNCHED   = 'crunched';

    // Keep using "simple" string for the backward compatibility
    const OUTPUT_FORMATTING_EXPANDED   = 'simple';

    /**
     * Please use OUTPUT_FORMATTING_EXPANDED instead of this constant.
     * This constant is kept only for the backward compatibility.
     *
     * @deprecated deprecated since version 1.2.0
     */
    const OUTPUT_FORMATTING_SIMPLE = self::OUTPUT_FORMATTING_EXPANDED;

    /**
     * Compiler object
     *
     * @var Sass
     */
    protected $scssc;

    /**
     * Publish and register compiled CSS file.
     * Compile/recompile source SCSS file if needed.
     *
     * Optionally can publish compiled CSS file inside
     * specific published directory.
     * It's helpful when CSS code has relative references to other
     * resources (images/fonts) and when these resources are also published
     * using Yii asset manager. This method allows to publish compiled CSS files
     * along with other resources to make relative references work.
     *
     * E.g.:
     * "image.jpg" is stored inside path alias "application.files.images"
     * Somewhere in the code the following is called during page generation:
     * Yii::app()->assetManager->publish(
     *     Yii::getPathOfAlias('application.files')
     * );
     * SCSS file has the following code:
     * background-image: url(../images/image.jpg);
     * Then the correct call of the method will be:
     * Yii::app()->sass->register(
     *     'path-to-scss-file.scss',
     *     '',
     *     'application.files',
     *     'css_compiled'
     * );
     *
     * @param string $sourcePath Path to the source SCSS file
     * @param string $media Media that the CSS file should be applied to.
     *        If empty, it means all media types
     * @param string $insidePublishedDirectory Path to the directory with
     *        resource files which is published somewhere in the application
     *        explicitly.
     *        Default is null which means that CSS file will be published
     *        separately.
     * @param string $subDirectory Subdirectory for the CSS file within publicly
     *        available location. Default is null
     * @param boolean $hashByName Must be the same
     *        as in the CAssetManager::publish() call
     *        for $insidePublishedDirectory.
     *        See CAssetManager::publish() for details.
     *        "defaultHashByName" plugin parameter's value is used by default.
     * @see CAssetManager::publish()
     */
    public function register(
        $sourcePath,
        $media = '',
        $insidePublishedDirectory = null,
        $subDirectory = null,
        $hashByName = null
    ) {
        $publishedPath = $this->publish(
            $sourcePath,
            $insidePublishedDirectory,
            $subDirectory,
            $hashByName
        );
        Yii::app()->clientScript->registerCssFile($publishedPath, $media);
    }

    /**
     * Publish compiled CSS file.
     * Compile/recompile source SCSS file if needed.
     * Optionally can publish compiled CSS file inside
     * specific published directory.
     * It's helpful when CSS code has relative references to other
     * resources (images/fonts) and when these resources are also published
     * using Yii asset manager. This method allows to publish compiled CSS files
     * along with other resources to make relative references work.
     * E.g.:
     * "image.jpg" is stored inside path alias "application.files.images"
     * Somewhere in the code the following is called during page generation:
     * Yii::app()->assetManager->publish(
     *     Yii::getPathOfAlias('application.files')
     * );
     * SCSS file has the following code:
     * background-image: url(../images/image.jpg);
     * Then the correct call of the method will be:
     * Yii::app()->sass->publish(
     *     'path-to-scss-file.scss',
     *     'application.files',
     *     'css_compiled'
     * );
     * @param string $sourcePath Path to the source SCSS file
     * @param string $insidePublishedDirectory Path to the directory
     *        with resource files which is published somewhere
     *        in the application explicitly.
     *        Default is null which means that CSS file
     *        will be published separately.
     * @param string $subDirectory Subdirectory for the CSS file
     *        within publicly available location. Default is null
     * @param boolean $hashByName Must be the same
     *        as in the CAssetManager::publish() call
     *        for $insidePublishedDirectory.
     *        See CAssetManager::publish() for details.
     *        "defaultHashByName" plugin parameter's value is used by default.
     * @return string URL of the published CSS file
	 * @throws CException
     * @see CAssetManager::publish()
     */
    public function publish(
        $sourcePath,
        $insidePublishedDirectory = null,
        $subDirectory = null,
        $hashByName = null
    ) {
        $compiledFile = $this->getCompiledFile($sourcePath);

        if (empty($insidePublishedDirectory)) {
            return Yii::app()->assetManager->publish(
                $compiledFile,
                is_null($hashByName) ? $this->defaultHashByName : $hashByName
            );
        } else {
            return $this->publishInside(
                $compiledFile,
                $insidePublishedDirectory,
                $subDirectory,
                $hashByName
            );
        }
    }

    /**
     * Get path to the compiled CSS file,
     * compile/recompile source file if needed.
     *
     * @param string $sourcePath Path to the source SCSS file
     * @throws CException
     * @return string
     */
    public function getCompiledFile($sourcePath) {
        $cssPath = $this->getCompiledCssFilePath($sourcePath);

        if ($this->autoAddCurrentDirectoryAsImportPath) {
            // Theme sass
            if ($theme = EO::app()->getTheme()) {
            	if (!empty($theme->basePath)) {
            		$this->compiler->addImportPath($theme->basePath);
	            }
            }

        	// Project sass
            $this->compiler->addImportPath(dirname($sourcePath));

            // Vendor sasss
            $this->compiler->addImportPath(YiiBase::getPathOfAlias('vendor.digizijn'));
        }

        if ($this->isCompilationNeeded($sourcePath)) {
            $compiledCssCode = $this->compile($sourcePath);

            if (file_put_contents($cssPath, $compiledCssCode, LOCK_EX) === false) {
                throw new CException(
                    'Can not write the compiled CSS file: ' . $cssPath
                );
            }

            if (!chmod($cssPath, $this->writableFilePermissions)) {
                throw new CException(
                    'Can not chmod('
                    . decoct($this->writableFilePermissions)
                    . ') the compiled CSS file: ' . $cssPath
                );
            }

            $this->saveParsedFilesInfoToCache($sourcePath);
        }
        return $cssPath;
    }

    /**
     * Compile SCSS file
     *
     * @param string $sourcePath
     * @throws CException
     * @return string Compiled CSS code
     */
    public function compile($sourcePath) {
        $sourceCode = file_get_contents($sourcePath);
        if ($sourceCode === false) {
            throw new CException(
                'Can not read the source SCSS file: ' . $sourcePath
            );
        }

        $compiledCssCode = $this->compiler->compile(ltrim($sourceCode));

        if (!defined('YII_DEBUG') || !YII_DEBUG) {
			$compiledCssCode 		= (new Autoprefixer($compiledCssCode))->compile();
        }

        return $compiledCssCode;
    }

    /**
     * Get compiler
     * Loads required files on initial request
     *
     * @return Sass
     */
    public function getCompiler()
    {
        if (!$this->scssc) {
            $this->scssc = new ExtendedScssc();

            if (YII_DEBUG) {
            	$this->scssc->setComments(true);
                $this->scssc->setEmbed(true);
            } else {
            	$this->scssc->setComments(false);
            	$this->scssc->setEmbed(false);
            }

	        if (!empty($this->importPaths)) {
                $this->scssc->setImportPaths($this->importPaths); // FIXME callables
	        }

            $this->setupOutputFormatting($this->scssc);

            // setMapPath
	        // setMapRoot
	        // setImporter
	        // setFunctions -> asset path functie maken?
        }
        return $this->scssc;
    }

    /**
     * Publish compiled CSS file inside specific published directory
     * Helpful when CSS code has relative references to other
     * resources (images/fonts) and when these resources are also published
     * using Yii asset manager
     *
     * @param string $compiledFile Path to the already compiled CSS file
     * @param string $insidePublishedDirectory Path to the directory
     *        with resource files which is published somewhere
     *        in the application explicitly.
     *        Default is null which means that CSS file
     *        will be published separately.
     * @param string $subDirectory Subdirectory for the CSS file within
     *        publicly available location.
     *        Default is null
     * @param boolean $hashByName Must be the same
     *        as in the CAssetManager::publish() call
     *        for $insidePublishedDirectory.
     *        See CAssetManager::publish() for details.
     *        "defaultHashByName" plugin parameter's value is used by default.
     * @throws CException
     * @return string URL of the published CSS file
     * @see CAssetManager::publish()
     */
    protected function publishInside(
        $compiledFile,
        $insidePublishedDirectory = null,
        $subDirectory = null,
        $hashByName = null
    ) {
        $hashByName = is_null($hashByName)
            ? $this->defaultHashByName
            : $hashByName;

        $insidePublishedDirectory = trim($insidePublishedDirectory, '/\\');
        $insidePublishedDirectoryRealPath = Yii::getPathOfAlias(
            $insidePublishedDirectory
        );
        $targetPath = Yii::app()->assetManager
            ->getPublishedPath($insidePublishedDirectoryRealPath, $hashByName)
            . DIRECTORY_SEPARATOR;
        if (!$targetPath) {
            throw new CException(
                'Directory with alias "' . $insidePublishedDirectory
                . '" doesn\'t exist. ' . 'Path with converted aliases: "'
                . $insidePublishedDirectoryRealPath . '"'
            );
        }

        $subDirectoryUrlSection = '';
        if (!empty($subDirectory)) {
            $subDirectory = trim($subDirectory, '/\\');
            $targetPath = $this->getWritableDirectoryPath(
                $targetPath . $subDirectory
            );
            $subDirectoryUrlSection = $subDirectory . '/';
        }

        $basename = basename($compiledFile);
        $targetFile = $targetPath . $basename;
        if (
            !file_exists($targetFile)
            or filemtime($compiledFile) !== filemtime($targetFile)
        ) {
            if (!copy($compiledFile, $targetFile)) {
                throw new CException(
                    'Can not copy the compiled file "' . $compiledFile
                    . '" to the "' . $targetPath . '" directory'
                );
            }

            if (!chmod($targetFile, $this->writableFilePermissions)) {
                throw new CException(
                    'Can not chmod(' . decoct($this->writableFilePermissions)
                    . ') the copied file with a compiled CSS code: '
                    . $targetFile
                );
            }
        }

        return Yii::app()->assetManager->getPublishedUrl(
            $insidePublishedDirectoryRealPath,
            $hashByName
        ) . '/' . $subDirectoryUrlSection . $basename;
    }

    /**
     * Setup compiler output formatting
     * @param Sass $compiler
     * @throws CException
     */
    protected function setupOutputFormatting($compiler) {
        // setIndent not available in php 8.0 compiled version of sass.so
        if (version_compare(phpversion(), '8.0', '<')) {
            if (YII_DEBUG) {
                $compiler->setIndent(true);
            } else {
                $compiler->setIndent(true);
            }
        }

        $formatting = array(
            self::OUTPUT_FORMATTING_NESTED      => Sass::STYLE_NESTED,
            self::OUTPUT_FORMATTING_COMPRESSED  => Sass::STYLE_COMPRESSED,
            self::OUTPUT_FORMATTING_EXPANDED    => Sass::STYLE_EXPANDED,
            self::OUTPUT_FORMATTING_CRUNCHED    => Sass::STYLE_COMPACT,
        );
        if (in_array($this->compilerOutputFormatting, array_keys($formatting))) {
            $compiler->setStyle(
                $formatting[$this->compilerOutputFormatting]
            );
        } else {
            throw new CException(
                'Unknown output formatting: ' . $this->compilerOutputFormatting
            );
        }
    }

    /**
     * Set import paths for compiler.
     * Paths will be used for @import Sass method.
     * Each path can be a filesystem path.
     * Or an Yii path with application aliases (like "application").
     *
     * @param array|string $paths Single import path or a list of paths
     */
    protected function setImportPaths($paths) {
        return $this->scssc->addImportPaths((array)$paths);
    }

    /**
     * Save a list of parsed files to the cache
     * with the time files were last modified.
     * Must be called right after the compilation.
     *
     * @param string $sourcePath Path to the source SCSS file
     */
    protected function saveParsedFilesInfoToCache($sourcePath)
    {
        $parsedFiles = $this->compiler->getParsedFiles();
        $parsedFiles[$sourcePath] = filemtime($sourcePath);

        $info = $this->getCompiledInfo(
        	$parsedFiles,
	        $this->autoAddCurrentDirectoryAsImportPath,
	        $this->compiler->getImportPaths(),
	        $this->compilerOutputFormatting
        );

        $pathInfo = $info;
        unset($pathInfo['compiledFiles']);
        $this->cacheSet($this->getCacheCompiledPrefix() . $sourcePath.'-'.md5(serialize($pathInfo)), $info);
    }


	public function getCompiledInfo(array $parsedFiles = [], bool $autoAddCurrentDirectoryAsImportPath = true, array $importPaths = [], $compilerOutputFormatting = 'nested') {
        $info = array(
            'compiledFiles' => $parsedFiles,
            'autoAddCurrentDirectoryAsImportPath'   => $autoAddCurrentDirectoryAsImportPath,
            'importPaths'                           => $importPaths,
            'compilerOutputFormatting'              => $compilerOutputFormatting,
        );

        return $info;
    }

    /**
     * Get path to a compiled CSS file
     *
     * @param string $sourcePath Path to a source SCSS file
     * @return string
     */
    protected function getCompiledCssFilePath($sourcePath)
    {
        // Add 8 last characters from the hash string
        // to prevent overwriting of previously compiled files
        // with the same basename but from another source directory
        return $this->getWritableDirectoryPath($this->sassCompiledPath)
            . basename($sourcePath, '.scss')
            . '-'
            . substr(md5($sourcePath), -8)
            . '.css';
    }

    /**
     * Does source SCSS file need to be compiled/recompiled
     *
     * @param string $path Path to a source SCSS file
     * @return boolean
     */
    protected function isCompilationNeeded($path)
    {
        if ($this->forceCompilation) {
            return true;
        }

        if (!file_exists($this->getCompiledCssFilePath($path))) {
            return true;
        }

        if (!$this->allowOverwrite) {
            return false;
        }

        if ($this->isLastCompilationEnvironmentChanged($path)) {
            return true;
        }

        return false;
    }

    /**
     * Is the previous compilation environment changed for specified SCSS file.
     * Check component's settings and modification time of imported files.
     *
     * @param string $path Path to a source SCSS file
     * @return boolean
     */
    protected function isLastCompilationEnvironmentChanged($path)
    {
        $parsedFiles = $this->compiler->getParsedFiles();
        $parsedFiles[$path] = filemtime($path);

        $info = $this->getCompiledInfo(
        	$parsedFiles,
	        $this->autoAddCurrentDirectoryAsImportPath,
	        $this->compiler->getImportPaths(),
	        $this->compilerOutputFormatting
        );

        $pathInfo = $info;
        unset($pathInfo['compiledFiles']);
        $compiledInfo = $this->cacheGet(
            $this->getCacheCompiledPrefix() . $path . '-'. md5(serialize($pathInfo))
        );

        $fieldsToCheckForChangedValue = array(
            'autoAddCurrentDirectoryAsImportPath',
            'compilerOutputFormatting',
        );
        foreach ($fieldsToCheckForChangedValue as $field) {
            if (!isset($compiledInfo[$field]) or
                $compiledInfo[$field] !== $this->$field) {
                return true;
            }
        }

        if (
            !isset($compiledInfo['importPaths']) or
            $compiledInfo['importPaths'] != $this->compiler->getImportPaths()
        ) {
            return true;
        }

        if (
            empty($compiledInfo['compiledFiles'])
            or !is_array($compiledInfo['compiledFiles'])
        ) {
            return true;
        }

        foreach ($compiledInfo['compiledFiles'] as $compiledFile => $previousModificationTime) {
        	if (!empty($compiledFile)) {
        		 if (substr($compiledFile, -5) !== '.scss') {
        		 	$compiledFile .= '.scss';
				 }

				if (file_exists($compiledFile) && filemtime($compiledFile) > $previousModificationTime) {
					return true;
				}
			}
        }

        return $this->forceCompilation;
    }

    /**
     * Get prefix for cache entries
     *
     * @return string
     */
    protected function getCacheCompiledPrefix()
    {
        return 'sass-compiled-';
    }

    /**
     * Get path of the writable directory
     * Create/chmod directory if needed
     *
     * @param string $path
     * @throws CException
     * @return string
     */
    protected function getWritableDirectoryPath($path)
    {
        $parsedAlias = YiiBase::getPathOfAlias($path);
        if ($parsedAlias !== false) {
            $path = $parsedAlias;
        }
        if (!is_dir($path)) {
            if (!mkdir($path, $this->writableDirectoryPermissions, true)) {
                throw new CException('Can not create directory: ' . $path);
            }
        }

        if (!chmod($path, $this->writableDirectoryPermissions)) {
            throw new CException(
                'Can not chmod(' . decoct($this->writableDirectoryPermissions)
                . ') directory: ' . $path
            );
        }

        return rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * Save a value to the cache.
     * Uses Yii cache if available.
     * Writes to a yii-sass cache file otherwise.
     *
     * @param string $name
     * @param mixed $value
     * @throws CException
     * @return boolean
     */
    protected function cacheSet($name, $value)
    {
        if ($this->getCacheComponent()) {
            return $this->getCacheComponent()->set($name, $value, 604800);
        }
        $path = $this->getCachePathForName($name);
        if (!file_put_contents($path, serialize($value), LOCK_EX)) {
            throw new CException('Can not write to the cache file: ' . $path);
        }

        if (!chmod($path, $this->writableFilePermissions)) {
            throw new CException(
                'Can not chmod(' . decoct($this->writableFilePermissions)
                . ') the cache file: ' . $path
            );
        }

        return true;
    }

    /**
     * Get a value from the cache.
     * Uses Yii cache if available.
     * Looks for a yii-sass cache file otherwise.
     *
     * @param string $name
     * @return mixed Cached value or false if entry is not found
     */
    protected function cacheGet($name)
    {
        if ($this->getCacheComponent()) {
            return $this->getCacheComponent()->get($name);
        }
        $path = $this->getCachePathForName($name);
        if (is_readable($path)) {
            return unserialize(file_get_contents($path));
        }
        return false;
    }

    /**
     * Get path for an yii-sass cache entry
     *
     * @param string $name
     * @return string
     */
    protected function getCachePathForName($name)
    {
        $maxFileLength = 255;
        $suffix = md5($name) . '.bin';
        $convertedName = basename($name);
        $convertedName = preg_replace(
            '/[^A-Za-z0-9\_\.]+/',
            '-',
            $convertedName
        );
        $convertedName = trim($convertedName, '-');
        $convertedName = substr(
            $convertedName,
            0,
            $maxFileLength - strlen('-' . $suffix)
        );
        $convertedName = strtolower($convertedName);
        if ($convertedName) {
            $convertedName .= '-';
        }

        return $this->getWritableDirectoryPath($this->cachePath)
            . $convertedName . $suffix;
    }

    /**
     * @return ICache Yii caching component
     */
    protected function getCacheComponent()
    {
        return $this->cacheComponentId ? Yii::app()->getComponent($this->cacheComponentId) : null;
    }
}
