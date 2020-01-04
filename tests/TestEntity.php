<?php

namespace InWeb\Media\Tests;

use Intervention\Image\Constraint;
use Intervention\Image\Image;
use InWeb\Base\Entity;
use InWeb\Media\Thumbnail;
use InWeb\Media\WithImages;

class TestEntity extends Entity
{
    use WithImages;

    /**
     * @return array
     */
    public function getImageThumbnails()
    {
        return [
            'original' => new Thumbnail(function (Image $image, Entity $object) {
                return $image->resize(20, 20, function (Constraint $constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })->resizeCanvas(20, 20);
            }),
            'test'     => new Thumbnail(function (Image $image, Entity $object) {
                return $image->resize(100, 100);
            }),
            'test2'    => new Thumbnail(function (Image $image, Entity $object) {
                return $image->resize(15, 15);
            }, true)
        ];
    }
}
