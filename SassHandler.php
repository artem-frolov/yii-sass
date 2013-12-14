<?php

/**
 * Sass Handler
 *
 * Compiles .scss file(s) on-the-fly
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
     * Yii aliases can be used.
     * Defaults to 'application.runtime.sass-cache'
     * 
     * @var string
     */
    public $cachePath = 'application.runtime.sass-cache';
    
    /**
     * Path and filename of scss.inc.php
     * Defaults to relative location in Composer's vendor directory:
     * dirname(__FILE__) . "/../../leafo/scssphp/scss.inc.php" 
     * 
     * @var string
     */
    public $compilerPath;
    
    /**
     * Path and filename of scss.inc.php
     * Defaults to relative location in Composer's vendor directory:
     * dirname(__FILE__) . "/../../leafo/scssphp-compass/compass.inc.php" 
     * 
     * @var string
     */
    public $compassPath;
    
    /**
     * Enable Compass support.
     * Automatically add required import paths and functions.
     * 
     * @var boolean
     */
    public $enableCompass = true;
    
    /**
     * Path to the directory with compiled CSS files.
     * Will be created automatically if doesn't exist.
     * Will be chmod'ed if it's not writable by script.
     * Yii aliases can be used.
     * Defaults to 'application.runtime.sass-compiled'
     * 
     * @var string
     */
    public $sassCompiledPath = 'application.runtime.sass-compiled';
    
    /**
     * Force recompilation on each request.
     * 
     * False value means that compilation will be done only if 
     * source .scss file or related imported files have been
     * changed after previous compilation.
     * 
     * Defaults to false
     * 
     * @var boolean
     */
    public $forceCompile = false;
    
    /**
     * Turn on/off overwriting of already compiled CSS files.
     * Will be ignored if $this->forceCompile is true.
     * 
     * True value means that compiled CSS file will be overwriten
     * if the source .scss file or related imported files have
     * been changed after previous compilation.
     * 
     * False value means that compilation will be done only if
     * output CSS file doesn't exist.
     * 
     * Defaults to true
     * 
     * @var boolean
     */
    public $allowOverwrite = true;
    
    /**
     * Automatically add directory of .scss file being processed
     * as an import path for the @import Sass directive.
     * Defaults to true
     * 
     * @var boolean
     */
    public $autoAddImportPath = true;
    
    /**
     * Chmod permissions used for creating/updating of writable
     * directories for cache files and compiled CSS files.
     * Mind the leading zero for octal values.
     * Defaults to 0644
     * 
     * @var integer
     */
    public $writableDirectoryPermissions = 0644;
    
    /**
     * Compiler object
     * 
     * @var ExtendedScssc
     */
    protected $scssc;

    /**
     * Initialize component
     */
    public function init()
    {
        if (!$this->compilerPath) {
            $this->compilerPath = dirname(__FILE__) . '/../../leafo/scssphp/scss.inc.php';
        }

        if (!$this->compassPath) {
            $this->compassPath = dirname(__FILE__) . "/../../leafo/scssphp-compass/compass.inc.php";
        }
        
        parent::init();
    }
    
    /**
     * Compile, publish and register CSS compiled file
     * 
     * @param string $path Path to the source .scss file
     * @param string $media Media that the CSS file should be applied to. If empty, it means all media types.
     */
    public function register($path, $media = '')
    {
        $published = $this->publish($path);
        Yii::app()->clientScript->registerCssFile($published, $media);
    }

    /**
     * Compile and publish compiled CSS file
     * 
     * @param string $path Path to the source .scss file
     * @return string Path to the published CSS file
     */
    public function publish($path)
    {
        $compiled = $this->compile($path);
        return Yii::app()->assetManager->publish($compiled);
    }

    /**
     * Compile .scss file
     * 
     * @param string $sourcePath
     * @throws CException
     * @return string
     */
    public function compile($sourcePath)
    {
        $cssPath = $this->getCompiledCssFilePath($sourcePath);
        if ($this->isRecompilationNeeded($sourcePath)) {
            if ($this->autoAddImportPath) {
                $originalImportPaths = $this->compiler->getImportPaths();
                $this->compiler->addImportPath(dirname($sourcePath));
            }
            
            $sourceCode = file_get_contents($sourcePath);
            if ($sourceCode === false) {
                throw new CException('Can not read from the file: ' . $sourcePath);
            }
            
            $compiledCssCode = $this->compiler->compile($sourceCode);

            if ($this->autoAddImportPath) {
                $this->compiler->setImportPaths($originalImportPaths);
            }
            
            if (!file_put_contents($cssPath, $compiledCssCode, LOCK_EX)) {
                throw new CException('Can not write to the file: ' . $cssPath);
            }
            
            $this->saveParsedFilesInfoToCache($sourcePath);
        }
        return $cssPath;
    }
    
    /**
     * Get compiler
     * Loads required file on initial request
     * 
     * @return ExtendedScssc
     */
    public function getCompiler()
    {
        if (!$this->scssc) {
            if (is_readable($this->compilerPath)) {
                require_once $this->compilerPath;
            }
            require_once dirname(__FILE__) . '/ExtendedScssc.php';
            $this->scssc = new ExtendedScssc();
            if ($this->enableCompass) {
                if (is_readable($this->compassPath)) {
                    require_once $this->compassPath;
                }
                new scss_compass($this->scssc);
            }
        }
        return $this->scssc;
    }
    
    /**
     * Save list of parsed files with the time files were last modified to the cache
     * Must be called right after the compilation.
     * 
     * @param string $sourcePath Path to the source .scss file
     */
    protected function saveParsedFilesInfoToCache($sourcePath)
    {
        $parsedFiles = $this->compiler->getParsedFiles();
        $parsedFiles[] = $sourcePath;
        foreach ($parsedFiles as $file) {
            $parsedFilesWithTime[$file] = filemtime($file);
        }
        
        $this->cacheSet($this->getCacheCompiledPrefix() . $sourcePath, $parsedFilesWithTime);
    }
    
    /**
     * Get path to the compiled CSS file
     * 
     * @param string $sourcePath Path to the source .scss file
     * @return string
     */
    protected function getCompiledCssFilePath($sourcePath)
    {
        return $this->getWritableDirectoryPath($this->sassCompiledPath) . basename($sourcePath, '.scss') . '.css';
    }
    
    /**
     * Is source .scss file needs to be recompiled
     * 
     * @param string $path Path to the source .scss file
     * @return boolean
     */
    protected function isRecompilationNeeded($path)
    {
        if ($this->forceCompile) {
            return true;
        }
        
        if (!file_exists($this->getCompiledCssFilePath($path))) {
            return true;
        }
        
        if ($this->allowOverwrite) {
            $compiledFiles = $this->cacheGet($this->getCacheCompiledPrefix() . $path);
            if ($compiledFiles && is_array($compiledFiles)) {
                foreach ($compiledFiles as $compiledFile => $compiledTime) {
                    if (filemtime($compiledFile) != $compiledTime) {
                        return true;
                    }
                }
            }
            else {
                return true;
            }
        }
        return false;
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
        if (!is_writable($path)) {
            if (!chmod($path, $this->writableDirectoryPermissions)) {
                throw new CException('Can not chmod(' . decoct($this->writableDirectoryPermissions) . ') directory: ' . $path);
            }
        }
        return rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * Set cache value.
     * Uses Yii cache if available.
     * Writes to the file otherwise.
     * 
     * @param string $name
     * @param mixed $value
     * @throws CException
     * @return boolean
     */
    protected function cacheSet($name, $value)
    {
        if (Yii::app()->cache) {
            return Yii::app()->cache->set($name, $value);
        }
        $path = $this->getCachePathForName($name);
        if (!file_put_contents($path, serialize($value), LOCK_EX)) {
            throw new CException('Can not write to the cache file: ' . $path);
        }
        return true;
    }

    /**
     * Get cache value.
     * Uses Yii cache if available.
     * Writes to the file otherwise.
     * 
     * @param string $name
     * @return mixed Cache value or false if entry is not found
     */
    protected function cacheGet($name)
    {
        if (Yii::app()->cache) {
            return Yii::app()->cache->get($name);
        }
        $path = $this->getCachePathForName($name);
        if (is_readable($path)) {
            return unserialize(file_get_contents($path));
        }
        return false;
    }
    
    /**
     * Get path for the cache entry
     * 
     * @param string $name
     * @return string
     */
    protected function getCachePathForName($name)
    {
        $maxFileLength = 255;
        $suffix = md5($name) . '.bin';
        $convertedName = basename($name);
        $convertedName = preg_replace('/[^A-Za-z0-9\_\.]+/', '-', $convertedName);
        $convertedName = trim($convertedName, '-');
        $convertedName = substr($convertedName, 0, $maxFileLength - strlen('-' . $suffix));
        $convertedName = strtolower($convertedName);
        if ($convertedName) {
            $convertedName .= '-';
        }
        
        return $this->getWritableDirectoryPath($this->cachePath) . $convertedName . $suffix;
    }
}
