<?php

namespace InWeb\Media\Images;

use Illuminate\Support\Str;
use Intervention\Image\Exception\NotFoundException;
use InWeb\Base\Entity;

/**
 * Trait WithImages
 * @property Images images
 * @property boolean imagesAutoName
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
     * @param bool $forAnyLanguage
     * @return Images
     */
    public function images($forAnyLanguage = false)
    {
        $instance = $this->newRelatedInstance(Image::class);

        $foreignKey = 'object_id';

        $localKey = $this->getKeyName();

        $query = (new Images(
            $instance->newQuery(), $this, 'model', $foreignKey, $localKey
        ))->with('object')->setObject($this)->where([
            'model' => get_class($this)
        ])->orderBy('position');

        if (! $forAnyLanguage) {
            $query->where(function ($q) {
                $q->whereNull('language');
                $q->orWhere('language', \App::getLocale());
            });
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

    /**
     * @return Thumbnail[]
     */
    public function getImageThumbnails()
    {
        return [];
    }

    public function onlyForMainImage()
    {
        return false;
    }

    public function getImageTypes()
    {
        return null;
    }

    /**
     * @param string $type
     * @return Thumbnail|null
     */
    public function getImageThumbnail($type)
    {
        return $this->getImageThumbnails()[$type] ?? null;
    }

    /**
     * @param string $type
     * @return bool
     */
    public function imageThumbnailExists($type)
    {
        return isset($this->getImageThumbnails()[$type]);
    }

    public function getImagesDir()
    {
        return 'images/' . $this->getModelName() . '/' . $this->id;
    }

    /**
     * @param string $image
     * @param string $type
     * @return string
     */
    public function getImagePath($image, $type = 'original')
    {
        return $this->getImageDir($type) . '/' . $image;
    }

    public function getImageDir($type = 'original')
    {
        return $this->getImagesDir() . '/' . $type;
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

    public function getImageAttribute()
    {
        return optional($this->images)->first(function($image) {
            return $image->isMain();
        });
    }

    /**
     * @return Image
     */
    public function mainImage()
    {
        return $this->images()->where('main', '=', '1')->first();
    }
}
