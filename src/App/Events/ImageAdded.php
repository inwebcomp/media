<?php

namespace InWeb\Media\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ImageAdded
{
    use Dispatchable;

    public $image;

    /**
     * @param \InWeb\Media\Image $image
     */
    public function __construct($image)
    {
        $this->image = $image;
    }
}
