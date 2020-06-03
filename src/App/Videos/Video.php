<?php

namespace InWeb\Media\Videos;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InWeb\Base\Entity;
use InWeb\Base\Traits\Positionable;
use InWeb\Media\BindedToModelAndObject;
use InWeb\Media\Images\WithImages;
use Spatie\EloquentSortable\Sortable;

/**
 * @property string filename
 * @property boolean main
 * @property string mimeType
 * @property WithVideos|Entity object
 */
class Video extends Entity implements Sortable
{
    use BindedToModelAndObject,
        Positionable,
        WithImages;

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
        return $this->getUrl();
    }

    /**
     * @return $this
     */
    public function setUniqueName()
    {
        $this->filename = static::getUniqueName($this->filename, $this->model, $this->object_id);

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
        while (static::where([
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
     * @return Video
     */
    public static function new($file, $filename = null)
    {
        $image = new static();

        $image->instance = $file;
        $image->filename = $filename ?: (is_string($file) ? basename($file) : $file->getClientOriginalName());

        return $image;
    }

    /**
     * @param string $type
     * @return string
     * @todo Remove type attribute
     */
    public function getDir($type = 'original')
    {
        return $this->object->getVideoDir($type);
    }

    public function getChunksDirectory()
    {
        return $this->getDir('_chunks');
    }

    public function getPath($type = 'original')
    {
        return $this->getDir($type) . '/' . $this->filename;
    }

    public function getUrl($preparedForEmbed = false)
    {
        if ($url = $this->getOriginal('url'))
            return $preparedForEmbed ? $this->prepareForEmbed($url) : $url;

        return static::url($this->getPath());
    }

    public function remove()
    {
        return $this->object->videos()->remove($this);
    }

    /**
     * @return $this
     */
    public function normalizeName()
    {
        $this->filename = static::getNormalizedName($this->filename);

        return $this;
    }

    /**
     * @param string $filename
     * @return string
     */
    public static function getNormalizedName($filename)
    {
        if (strpos($filename, '.') !== false) {
            $tmp = explode('.', $filename);
            $ext = array_pop($tmp);
            $result = implode('.', $tmp);
        } else {
            $ext = '';
            $result = $filename;
        }

        return Str::slug($result) . ($ext != '' ? '.' . $ext : '');
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
        } catch (\Exception $e) {
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

        $mainVideo = $this->object->mainVideo();

        if ($mainVideo) {
            $mainVideo->main = 0;
            $mainVideo->save();
        }

        $this->main = 1;
        $this->save();
    }

    public function isMain()
    {
        return $this->main == true;
    }

    public function isLocal()
    {
        return ! $this->getOriginal('url');
    }

    public function isEmbed()
    {
        return strpos($this->getUrl(true), 'https://www.youtube.com/embed/') !== false;
    }

    public function prepareForEmbed($url)
    {
        if ($match = 'youtube.com/watch?v=' and ($pos = strpos($url, $match)) !== false) {
            $id = substr($url, $pos + strlen($match), 11);
            return 'https://www.youtube.com/embed/' . $id;
        }

        return $url;
    }

    public function getMimeTypeAttribute()
    {
        if ($this->isLocal())
            return Storage::disk('public')->mimeType($this->getPath());

        $tmp = explode('.', $this->getUrl(true));
        $extension = end($tmp);

        if ($extension == 'mp4')
            return 'video/mp4';
        else if ($extension == 'webm')
            return 'video/webm';
        else if ($extension == 'mpeg')
            return 'video/mpeg';

        return null;
    }
}
