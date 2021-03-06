<?php

namespace InWeb\Media\Images;

use Closure;
use Exception;
use File;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InWeb\Base\Contracts\Cacheable;
use InWeb\Base\Entity;
use InWeb\Base\Traits\Positionable;
use InWeb\Media\BindedToModelAndObject;
use Spatie\EloquentSortable\Sortable;

/**
 * @property string filename
 * @property boolean main
 * @property null|string type
 * @property null|string language
 * @property string path
 * @property WithImages|Model object
 */
class Image extends Entity implements Sortable
{
    use BindedToModelAndObject,
        Positionable;

    protected static function boot()
    {
        parent::boot();

        static::updated(function ($model) {
            if ($model->object instanceof Cacheable)
                $model->object::clearCache($model->object);
        });
        static::deleting(function ($model) {
            if ($model->object instanceof Cacheable)
                $model->object::clearCache($model->object);
        });
        static::created(function ($model) {
            if ($model->object instanceof Cacheable)
                $model->object::clearCache($model->object);
        });
    }

    /**
     * @var UploadedFile|null
     */
    protected $instance;
    protected $appends = ['url'];
    protected $base64;
    protected $decodedBase64;

    protected $casts = [
        'main' => 'boolean'
    ];

    public static function url($path)
    {
        return url('storage/' . $path);
    }

    public function getPathAttribute()
    {
        return $this->getPath();
    }

    public function getUrlAttribute()
    {
        return static::url($this->path);
    }

    /**
     * @return $this
     */
    public function setUniqueName()
    {
        $this->filename = self::getUniqueName($this->filename, $this->model, $this->object_id);

        return $this;
    }

    /**
     * @param string $filename
     * @param string $model
     * @param int $object_id
     * @return string
     */
    public static function getUniqueName($filename, $model, $object_id)
    {
        if (strpos($filename, '.') !== false) {
            $tmp = explode('.', $filename);
            $ext = array_pop($tmp);
            $originalName = $filename = implode('.', $tmp);
        } else {
            $ext = '';
            $originalName = $filename;
        }

        $n = 2;
        while (Image::where([
            'object_id' => $object_id,
            'model'     => $model,
            'filename'  => $filename . ($ext != '' ? '.' . $ext : '')
        ])->exists()) {
            $filename = $originalName . '-' . $n++;
        }

        return $filename . ($ext != '' ? '.' . $ext : '');
    }

    /**
     * @return UploadedFile|string|null
     */
    public function getInstance()
    {
        return $this->instance;
    }

    /**
     * @param UploadedFile|string $file
     * @param null|string $filename
     * @return Image
     */
    public static function new($file, $filename = null)
    {
        $image = new Image();

        $image->instance = $file;

        $image->isBase64();

        $image->filename = $filename ?: (is_string($file) ? basename($file) : $file->getClientOriginalName());

        if (is_string($file)) {
            $image->filename = preg_replace("/(^.*?\.(jpg|jpeg|png|svg|gif|webp))(.*)$/i", '$1', $image->filename);
        }

        if (strlen($image->filename) > 200)
            $image->filename = substr($image->filename, 0, 200);

        return $image;
    }

    public function instanceForModify($type = 'original')
    {
        $disk = Storage::disk('public');

        $imageSource = $disk->path($this->getPath($type));

        return \Image::make($imageSource);
    }

    public function getDir($type = 'original')
    {
        return $this->object->getImageDir($type);
    }

    public function getPath($type = 'original')
    {
        return $this->getDir($type) . '/' . $this->filename;
    }

    public function getUrl($type = 'original')
    {
        return $this->url($this->getPath($type));
    }

    public function remove()
    {
        return $this->object->images()->remove($this);
    }

    /**
     * @param               $name
     * @param null|Closure $modifier
     * @return bool|\Intervention\Image\Image
     * @throws Exception
     * @todo Create thumbnails on ImageAddEvent
     */
    public function createThumbnail($name, $modifier = null)
    {
        $this->assertThumbnailExists($name);

        /** @var WithImages|Entity $object */
        $object = $this->object;
        $thumbnail = $object->getImageThumbnail($name);

        if (! $thumbnail->shouldCreateThumbnail($object, $this))
            return false;

        if ($thumbnail->isOnlyForMain() and ! $this->isMain())
            return false;

        if ($this->type and ! $thumbnail->isForType($this->type))
            return false;

        $function = $modifier ?? $thumbnail->getModifier();

        $disk = Storage::disk('public');

        $disk->makeDirectory($this->getDir($name));

        if ($name == 'original' and $this->getInstance()) {
            if (is_string($this->getInstance())) {
                $imageSource = $this->getInstance();
            } else {
                $imageSource = $this->getInstance()->getPathname();
            }
        } else {
            $imageSource = $disk->path($this->getPath());
        }

        $info = pathinfo($imageSource);

        if (isset($info['extension']) and $info['extension'] == 'svg') {
            File::copy($imageSource, $disk->path($this->getPath($name)));
            return true;
        }

        $image = \Image::make($imageSource);

        /** @var \Intervention\Image\Image $thumb */
        $thumb = $function($image, $object);

        $thumb->save(
            $disk->path($this->getPath($name)),
            $thumbnail->getQuality(),
            $thumbnail->getFormat()
        );

        return $thumb;
    }

    /**
     * @throws Exception
     */
    public function createThumbnails()
    {
        foreach ($this->object->getImageThumbnails() as $name => $thumbnail) {
            $this->createThumbnail($name);
        }
    }

    /**
     * @throws Exception
     */
    public function createMainThumbnails()
    {
        foreach ($this->object->getImageThumbnails() as $name => $thumbnail) {
            if ($thumbnail->isOnlyForMain())
                $this->createThumbnail($name);
        }
    }

    /**
     * @param $name
     * @return bool
     * @throws Exception
     */
    public function removeThumbnail($name)
    {
        $this->assertThumbnailExists($name);

        Storage::disk('public')->delete($this->getPath($name));

        return true;
    }

    /**
     * @throws Exception
     */
    public function removeThumbnails()
    {
        foreach ($this->object->getImageThumbnails() as $name => $thumbnail) {
            $this->removeThumbnail($name);
        }
    }

    /**
     * @throws Exception
     */
    public function removeMainThumbnails()
    {
        foreach ($this->object->getImageThumbnails() as $name => $thumbnail) {
            if ($thumbnail->isOnlyForMain())
                $this->removeThumbnail($name);
        }
    }

    /**
     * @param $name
     * @return bool|\Intervention\Image\Image
     * @throws Exception
     */
    public function recreateThumbnail($name)
    {
        $this->assertThumbnailExists($name);

        return $this->createThumbnail($name);
    }

    /**
     * @param $name
     * @throws Exception
     */
    public function assertThumbnailExists($name) : void
    {
        if (! $this->object->imageThumbnailExists($name)) {
            throw new Exception(
                'Thumbnail with name "' . $name . '" does not exists at model ' . $this->object->getMorphClass()
            );
        }
    }

    /**
     * @param bool $autoName
     * @return $this
     */
    public function normalizeName($autoName = false)
    {
        if (strpos($this->filename, '.') !== false) {
            $tmp = explode('.', $this->filename);
            $ext = array_pop($tmp);
            $filename = implode('.', $tmp);
        } else {
            $ext = '';
            $filename = $this->filename;
        }

        if ($autoName)
            $filename = $autoName;

        $this->filename = Str::slug($filename) . ($ext != '' ? '.' . $ext : '');

        return $this;
    }

    public function getBase64DecodedContent()
    {
        return $this->decodedBase64;
    }

    public function isBase64()
    {
        if ($this->base64 !== null)
            return $this->base64;

        try {
            $decoded = base64_decode($this->getInstance(), true);

            if (base64_encode($decoded) === $this->getInstance()) {
                $this->base64 = true;
                $this->decodedBase64 = $decoded;
                return true;
            } else {
                $this->base64 = false;
                return false;
            }
        } catch (Exception $e) {
            $this->base64 = false;
            return false;
        }
    }

    public static function mainColumn(Blueprint $table)
    {
        $table->boolean('main')->default(0);
    }

    /**
     * @throws Exception
     */
    public function setMain()
    {
        if ($this->isMain())
            return;

        $mainImage = $this->object->mainImage($this->type);

        if ($mainImage) {
            $mainImage->main = 0;
            $mainImage->save();
            $mainImage->removeMainThumbnails();
        }

        $this->main = 1;
        $this->save();
        $this->createMainThumbnails();
    }

    public function isMain()
    {
        return $this->main == true;
    }

    /**
     * @param $language
     */
    public function setLanguage($language)
    {
        $this->language = $language;
        $this->save();

        if ($this->object->images()->where('main', 1)->count() > 1) {
            $this->main = false;
            $this->save();
        }

        foreach (config('inweb.languages') as $language) {
            if (! $this->object->imagesForLanguage($language)->where('main', 1)->count()) {
                $image = $this->object->imagesForLanguage($language)->first();
                if ($image) {
                    $image->main = true;
                    $image->save();
                }
            }
        }
    }

    public function scopeType(Builder $query, $type)
    {
        return $query->where('type', $type);
    }
}
