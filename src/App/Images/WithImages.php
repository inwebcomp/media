<?php

namespace InWeb\Media\Images;

use Illuminate\Support\Str;
use Intervention\Image\Exception\NotFoundException;
use InWeb\Base\Entity;

/**
 * Trait WithImages
 * @property Images images
 * @property boolean imagesAutoName
 * @property Image|null image
 * @package InWeb\Media\Images
 */
trait WithImages
{
    protected static function bootWithImages()
    {
        static::deleting(function (Entity $model) {
            $model->images()->removeAll();
        });
    }

    public function getModelName()
    {
        return class_basename($this);
    }

    public function imageAutoName(Image $image = null)
    {
        $result = false;

        if ($this->title)
            $result = $this->title;
        else if ($this->slug)
            $result = $this->slug;
        else if ($this->name)
            $result = $this->name;

        if (! $result)
            return $this->getKey();

        return Str::slug($result);
    }

    /**
     * @param string|null $type
     * @param bool $forAnyLanguage
     * @return Images
     */
    public function images($type = null, $forAnyLanguage = false)
    {
        $instance = $this->newRelatedInstance(Image::class);

        $foreignKey = 'object_id';

        $localKey = $this->getKeyName();

        /** @var Images $query */
        $query = (new Images(
            $instance->newQuery(), $this, 'model', $foreignKey, $localKey
        ))->with('object')->setObject($this)->where([
            'model' => get_class($this)
        ])->orderBy('position')
          ->setType($type);

        if (! $forAnyLanguage) {
            $query->where(function ($q) {
                $q->whereNull('language');
                $q->orWhere('language', \App::getLocale());
            });
        }

        if ($type) {
            $query->where('type', $type);
        }

        return $query;
    }

    /**
     * @param string $language
     * @return Images
     */
    public function imagesForLanguage($language)
    {
        return $this->images(true)->where(function ($q) use ($language) {
            $q->whereNull('language');
            $q->orWhere('language', $language);
        });
    }

    public function hasImages()
    {
        return $this->images->isNotEmpty();
    }

    public function getDisk()
    {
        return 'public';
    }

    /**
     * @return Thumbnail[]
     */
    public function getImageThumbnails()
    {
        return [];
    }

    /**
     * @param string $thumbnail
     * @return Thumbnail|null
     */
    public function getImageThumbnail($thumbnail)
    {
        return $this->getImageThumbnails()[$thumbnail] ?? null;
    }

    /**
     * @param string $thumbnail
     * @return bool
     */
    public function imageThumbnailExists($thumbnail)
    {
        return isset($this->getImageThumbnails()[$thumbnail]);
    }

    public function getImagesDir()
    {
        return 'images/' . $this->getModelName() . '/' . $this->id;
    }

    /**
     * @param string $image
     * @param string $thumbnail
     * @return string
     */
    public function getImagePath($image, $thumbnail = 'original')
    {
        return $this->getImageDir($thumbnail) . '/' . $image;
    }

    public function getImageDir($thumbnail = 'original')
    {
        return $this->getImagesDir() . '/' . $thumbnail;
    }

    /**
     * @param int|string $imageName
     * @return Image
     * @throws NotFoundException
     */
    public function getImage($imageName)
    {
        if (is_integer($imageName)) {
            $image = Image::find($imageName);
        } else {
            $image = Image::where([
                'object_id' => $this->id,
                'model'     => get_class($this),
                'filename'  => $imageName
            ])->first();
        }

        if (! $image)
            throw new NotFoundException('Image could not be found [' . $imageName . ']');

        return $image;
    }

    /**
     * @return Image|null
     */
    public function getImageAttribute()
    {
        return optional($this->images)->first(function ($image) {
            return $image->isMain();
        });
    }

    public function image($type = null)
    {
        return optional($this->images)->first(function($image) use ($type) {
            return $image->isMain() && (! $type or $image->type == $type);
        });
    }

    /**
     * @param null $type
     * @return Image
     */
    public function mainImage($type = null)
    {
        $query = $this->images($type)->where('main', '=', '1');

        /** @var Image $image */
        $image = $query->first();

        return $image;
    }
}
