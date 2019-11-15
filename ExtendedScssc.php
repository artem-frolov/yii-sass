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

		$this->setImporter([$this, 'import']);
	}


	/*
	 * imp_path
	 * abs_path
	 * source
	 * srcmap
	 */
	public function import($arg) {
		// FIXME absoluut import path nodig
		if (!in_array($arg, $this->parsedFiles)) {
			$this->parsedFiles[] = $arg;
		}
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


	/**
	 * @return array
	 */
	public function getParsedFiles() {
		return $this->parsedFiles;
    }

}
