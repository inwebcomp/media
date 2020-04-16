<?php

namespace InWeb\Media\Images;

/**
 * Class Thumbnail
 * @package InWeb\Media
 * @property \Intervention\Image\Image image
 * @property WithImages object
 */
class Thumbnail
{
    private $modifier;
    /**
     * @var bool
     */
    private $onlyForMain;
    /**
     * @var int|null
     */
    private $quality;
    /**
     * @var string|null
     */
    private $format;

    public function __construct($modifier, $onlyForMain = false)
    {
        $this->modifier = $modifier;
        $this->onlyForMain = $onlyForMain;
    }

    /**
     * @param mixed $modifier
     */
    public function setModifier($modifier)
    {
        $this->modifier = $modifier;
    }

    /**
     * @return mixed
     */
    public function getModifier()
    {
        return $this->modifier;
    }

    /**
     * @return bool
     */
    public function isOnlyForMain()
    {
        return $this->onlyForMain;
    }

    /**
     * @param int $value
     * @return Thumbnail
     */
    public function setQuality($value)
    {
        $this->quality = $value;

        return $this;
    }

    /**
     * @param int $value
     * @return Thumbnail
     */
    public function setFormat($value)
    {
        $this->format = $value;

        return $this;
    }

    public function getQuality()
    {
        return $this->quality;
    }

    public function getFormat()
    {
        return $this->format;
    }
}
