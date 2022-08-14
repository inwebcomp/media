<?php

namespace InWeb\Media\Videos;

use InWeb\Base\Entity;

/**
 * Trait WithVideos
 * @package InWeb\Media\Videos
 */
trait WithVideos
{
    public static function videoModel()
    {
        return Video::class;
    }

    protected static function bootWithVideos()
    {
        static::deleting(function (Entity $model) {
            $model->videos()->removeAll();
        });
    }

    /**
     * @return Videos
     */
    public function videos()
    {
        $instance = $this->newRelatedInstance(static::videoModel());

        $foreignKey = 'object_id';

        $localKey = $this->getKeyName();

        return (new Videos(
            $instance->newQuery(), $this, 'model', $foreignKey, $localKey
        ))->with('object')->setObject($this)->where([
            'model' => get_class($this)
        ])->orderBy('position');
    }

    public function hasVideos()
    {
        return $this->videos->isNotEmpty();
    }

    public function getVideosDir()
    {
        return 'videos/' . $this->getModelName() . '/' . $this->id;
    }

    /**
     * @param string $video
     * @param string $type
     * @return string
     */
    public function getVideoPath($video, $type = 'original')
    {
        return $this->getVideoDir($type) . '/' . $video;
    }

    public function getVideoDir($type = 'original')
    {
        return $this->getVideosDir() . '/' . $type;
    }

    /**
     * @param int|string $videoName
     * @return static::videoModel()
     * @throws \Exception
     */
    public function getVideo($videoName)
    {
        if (is_integer($videoName)) {
            $video = static::videoModel()::find($videoName);
        } else {
            $video = static::videoModel()::where([
                'object_id' => $this->id,
                'model'     => get_class($this),
                'filename'  => $videoName
            ])->first();
        }

        if (! $video)
            throw new \Exception('Video could not be found [' . $videoName . ']');

        return $video;
    }

    public function getVideoAttribute()
    {
        return optional($this->videos)->first(function($video) {
            return $video->isMain();
        });
    }

    /**
     * @return static::videoModel()
     */
    public function mainVideo()
    {
        return $this->videos()->where('main', '=', '1')->first();
    }

    /**
     * @return array
     */
    public function videoExtraFormats(): array
    {
        return [];
    }

    /**
     * @return Variant[]
     */
    public function getVideoVariants() : array
    {
        return [];
    }

    public function getVideoVariant(string $variant) : ?Variant
    {
        return $this->getVideoVariants()[$variant] ?? null;
    }

    public function videoVariantExists(string $variant) : bool
    {
        return isset($this->getVideoVariants()[$variant]);
    }
}
