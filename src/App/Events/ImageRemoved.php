<?php

namespace InWeb\Media\Events;

use Illuminate\Foundation\Events\Dispatchable;
use InWeb\Media\Images\Image;

class ImageRemoved
{
    use Dispatchable;

    public $image;

    /**
     * @param Image $image
     */
    public function __construct($image)
    {
        $this->image = $image;
    }
}
