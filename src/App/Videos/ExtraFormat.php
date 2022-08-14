<?php

namespace InWeb\Media\Videos;

use FFMpeg\Format\Video\DefaultVideo;
use FFMpeg\Format\Video\WebM;
use FFMpeg\Format\Video\WMV;
use FFMpeg\Format\Video\X264;

class ExtraFormat
{
    private DefaultVideo|\Closure $format;
    private ?string               $extension;
    private ?\Closure             $modifier;
    private bool                  $notForOriginal = false;

    public function __construct(DefaultVideo|\Closure $format, $extension = null, $modifier = null)
    {
        $this->modifier = $modifier;
        $this->format = $format;
        $this->extension = $extension;
    }

    public function handle(\FFMpeg\Media\Video $video, Video $model) : void
    {
        if ($this->modifier) {
            ($this->modifier)($video, $model);
        }
    }

    public function getFFmpegFormat() : DefaultVideo
    {
        if (is_callable($this->format)) {
            return ($this->format)();
        }

        return $this->format;
    }

    public function getExtension()
    {
        if ($this->extension) {
            return $this->extension;
        }

        return match (get_class($this->format)) {
            WebM::class => 'webm',
            X264::class => 'mp4',
            WMV::class => 'wmv',
        };
    }

    public function notForOriginal() : static
    {
        $this->notForOriginal = true;

        return $this;
    }

    public function isForOriginal() : bool
    {
        return ! $this->notForOriginal;
    }
}
