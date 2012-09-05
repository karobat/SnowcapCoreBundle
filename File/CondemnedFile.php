<?php

namespace Snowcap\CoreBundle\File;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * This class is meant to signal a file deletion - used in forms (see Snowcap\CoreBundle\Form\Type\FileType
 */
class CondemnedFile extends UploadedFile {

    /**
     * @var string
     */
    private $path;

    /**
     * Override parent constructor
     */
    public function __construct()
    {
    }

    /**
     * @return bool
     */
    public function isValid()
    {
        return true;
    }

    /**
     * @param string $path
     */
    public function setPath($path) {
        $this->path = $path;
    }

    /**
     * @return string
     */
    public function getPathName() {
        return $this->path;
    }
}