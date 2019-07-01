<?php

namespace InWeb\Media;

use InWeb\Base\Entity;

trait WithContentImages
{
    protected static function bootWithContentImages()
    {
        static::deleting(function (Entity $model) {
            \Storage::disk('public')->deleteDirectory($model->contentImagesPath());
        });
    }

    public function contentImagesPath()
    {
        return 'contents/' . class_basename($this) . '/' . $this->getKey();
    }
}
