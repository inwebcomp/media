<?php

namespace InWeb\Media\Videos;

use FFMpeg\Format\Video\DefaultVideo;
use FFMpeg\Format\Video\WebM;
use FFMpeg\Format\Video\WMV;
use FFMpeg\Format\Video\X264;
use function PHPUnit\Framework\matches;

class ExtraFormat
{
    private \Closure     $handler;
    private DefaultVideo|\Closure $format;

    /**
     * @var string|null
     */
    private ?string $extension;

    public function __construct(DefaultVideo|\Closure $format, $handler, $extension = null)
    {
        $this->handler = $handler;
        $this->format = $format;
        $this->extension = $extension;
    }

    public function handle(\FFMpeg\Media\Video $video, Video $model) : void
    {
        ($this->handler)($video, $model);
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
}
