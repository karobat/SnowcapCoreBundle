<?php
namespace Snowcap\CoreBundle\Doctrine\Mapping;

use Doctrine\Common\Annotations\Annotation;
/**
 * @Annotation
 */
class File extends Annotation
{
    /** @var string */
    public $path;
    /** @var string */
    public $mappedBy;
    /** @var string */
    public $filename;
    /** @var string */
    public $nameCallback;
}
