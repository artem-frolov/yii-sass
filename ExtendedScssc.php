<?php
/**
 * Extended class for SCSS compiler
 */
class ExtendedScssc extends ScssPhp\ScssPhp\Compiler
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