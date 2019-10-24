<?php

namespace InWeb\Media;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InWeb\Admin\App\Models\Entity;

/**
 * @property string     filename
 * @property boolean    main
 * @property WithImages object
 */
class Image extends Entity
{
    use BindedToModelAndObject;
    /**
     * @var UploadedFile|null
     */
    protected $instance;
    protected $appends = ['url'];
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
     * @param int    $object_id
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
     * @param null|string         $filename
     * @return Image
     */
    public static function new($file, $filename = null)
    {
        $image = new Image();

        $image->instance = $file;
        $image->filename = $filename ?: (is_string($file) ? basename($file) : $file->getClientOriginalName());

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
     * @param null|\Closure $modifier
     * @return bool|\Intervention\Image\Image
     * @todo Create thumbnails on ImageAddEvent
     * @throws \Exception
     */
    public function createThumbnail($name, $modifier = null)
    {
        $this->assertThumbnailExists($name);

        /** @var WithImages $object */
        $object = $this->object;
        $thumbnail = $object->getImageThumbnail($name);

        if ($thumbnail->isOnlyForMain() and ! $this->isMain())
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
            \File::copy($imageSource, $disk->path($this->getPath($name)));
            return true;
        }

        $image = \Image::make($imageSource);

        $thumb = $function($image, $object);

        $thumb->save(
            $disk->path($this->getPath($name))
        );

        return $thumb;
    }

    public function createThumbnails()
    {
        foreach ($this->object->getImageThumbnails() as $name => $thumbnail) {
            $this->createThumbnail($name);
        }
    }

    public function createMainThumbnails()
    {
        foreach ($this->object->getImageThumbnails() as $name => $thumbnail) {
            if ($thumbnail->isOnlyForMain())
                $this->createThumbnail($name);
        }
    }

    public function removeThumbnail($name)
    {
        $this->assertThumbnailExists($name);

        Storage::disk('public')->delete($this->getPath($name));

        return true;
    }

    public function removeThumbnails()
    {
        foreach ($this->object->getImageThumbnails() as $name => $thumbnail) {
            $this->removeThumbnail($name);
        }
    }

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
     * @throws \Exception
     */
    public function recreateThumbnail($name)
    {
        $this->assertThumbnailExists($name);

        return $this->createThumbnail($name);
    }

    /**
     * @param $name
     * @throws \Exception
     */
    public function assertThumbnailExists($name) : void
    {
        if (! $this->object->imageThumbnailExists($name)) {
            throw new \Exception(
                'Thumbnail with name "' . $name . '" does not exists at model ' . $this->object->getMorphClass()
            );
        }
    }

    /**
     * @return $this
     */
    public function normalizeName()
    {
        if (strpos($this->filename, '.') !== false) {
            $tmp = explode('.', $this->filename);
            $ext = array_pop($tmp);
            $filename = implode('.', $tmp);
        } else {
            $ext = '';
            $filename = $this->filename;
        }

        $this->filename = str_slug($filename) . ($ext != '' ? '.' . $ext : '');

        return $this;
    }

    public function isBase64()
    {
        try {
            $decoded = base64_decode($this->getInstance(), true);

            if (base64_encode($decoded) === $this->getInstance()) {
                return true;
            } else {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    public static function mainColumn(Blueprint $table)
    {
        $table->boolean('main')->default(0);
    }

    public function setMain()
    {
        if ($this->isMain())
            return;

        $mainImage = $this->object->mainImage();

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
        return (bool) $this->main;
    }
}
