<?php

namespace InWeb\Media\Events;

use Illuminate\Foundation\Events\Dispatchable;
use InWeb\Media\Videos\Video;

class VideoRemoved extends \Event
{
    use Dispatchable;

    public $video;

    /**
     * @param Video $video
     */
    public function __construct($video)
    {
        $this->video = $video;
    }
}