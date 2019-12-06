<?php

/**
 * Extended class for SCSS compiler
 *
 * @author Artem Frolov <artem@frolov.net>
 * @link https://github.com/artem-frolov/yii-sass
 */
class ExtendedScssc extends Sass {
	protected $parsedFiles = [];


	public function __construct() {
		parent::__construct();

		parent::setFunctions([
			'baseUrl' => function() {
				EO::app()->getBaseUrl(true);
			},
			'relBaseUrl' => function() {
				EO::app()->getBaseUrl(false);
			},
			'basePath' => function() {
				EO::app()->getBasePath();
			},
		]);

        if (!defined('YII_DEBUG') || !YII_DEBUG) {
        	$this->setEmbed(true);
        }

//		parent::setIncludePath(YiiBase::getPathOfAlias('@webroot'));
		parent::setIncludePath($_SERVER['DOCUMENT_ROOT']);

		$this->setImporter([$this, 'import']);
	}


	/*
	 * imp_path
	 * abs_path
	 * source
	 * srcmap
	 */
	public function import($arg) {
		if (!array_key_exists($arg, $this->parsedFiles)) {
			$this->parsedFiles[$arg] = (new DateTime)->getTimestamp();
		}
	}

	/**
	 * @return array
	 */
	public function getParsedFiles() {
		return $this->parsedFiles;
  }


	/**
     * Get list of current import paths
     * @return array
     */
  public function getImportPaths() : array {
        return explode(':', $this->getIncludePath());
  }


	/**
	 * @param string $path
	 * @return bool
	 */
	public function addImportPath(string $path) : bool {
		$paths = $this->getImportPaths();

        $preparedPath = YiiBase::getPathOfAlias($path);
        if ($preparedPath === false || empty($preparedPath)) {
            $preparedPath = $path;
        }

		if (!in_array($preparedPath, $paths)) {
			$paths[] = $preparedPath;
		}

		return true;
    }


	/**
	 * @param string $path
	 * @return bool
	 */
	public function addImportPaths(array $paths) : bool {
		$success = true;
		foreach ($paths as $path) {
			$success &= $this->addImportPath($path);
		}

		return $success;
    }

	/**
	 * @param array $paths
	 */
	public function setImportPaths(array $paths) {
        $this->setIncludePath(implode(':', $paths));
    }


	public function setIncludePath($path) {
		parent::setIncludePath($path);
	}
}