<?php

/**
 * Extended class for SCSS compiler
 *
 * @author Artem Frolov <artem@frolov.net>
 * @link https://github.com/artem-frolov/yii-sass
 */
class ExtendedScssc extends \ScssPhp\ScssPhp\Compiler
{
    /**
     * Get list of current import paths
     *
     * @return array
     */
    public function getImportPaths()
    {
        return $this->importPaths;
    }
}
