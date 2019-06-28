<?php

namespace InWeb\Media\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ImagesPositionChanged
{
    use Dispatchable;
    /**
     * @var string
     */
    public $tags;

    /**
     * @var array|null
     */
    public $ids;

    /**
     * @param $tags
     * @param $ids
     */
    public function __construct($tags, $ids = null)
    {
        $this->tags = $tags;
        $this->ids = $ids;
    }
}
