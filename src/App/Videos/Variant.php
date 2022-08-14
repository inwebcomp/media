<?php

namespace InWeb\Media\Videos;

use InWeb\Base\Entity;

/**
 * Class Variant
 * @package InWeb\Media
 * @property \FFMpeg\Media\Video $video
 * @property Video $model
 */
class Variant
{
    private \Closure $modifier;
    private bool     $onlyForMain = false;
    private \Closure  $createIfClosure;
    private ?\Closure $formatModifier;

    public function __construct(\Closure $modifier, ?\Closure $formatModifier = null)
    {
        $this->modifier = $modifier;
        $this->formatModifier = $formatModifier;
    }

    public function setModifier(\Closure $modifier) : void
    {
        $this->modifier = $modifier;
    }

    public function getModifier() : \Closure
    {
        return $this->modifier;
    }

    public function getFormatModifier() : ?\Closure
    {
        return $this->formatModifier;
    }

    public function onlyForMain($value = true) : static
    {
        $this->onlyForMain = $value;

        return $this;
    }

    public function isOnlyForMain() : bool
    {
        return $this->onlyForMain;
    }

    public function shouldCreateVariant(Entity $object, Video $video) : bool
    {
        if (isset($this->createIfClosure) and is_callable($this->createIfClosure)) {
            return call_user_func($this->createIfClosure, $object, $video);
        }

        return true;
    }

    public function createIf(\Closure $closure) : static
    {
        $this->createIfClosure = $closure;

        return $this;
    }
}
