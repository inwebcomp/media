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
        if ($url = $this->getRawOriginal('url'))
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
        return ! $this->getRawOriginal('url');
    }

    public function isEmbed()
    {
        $url = $this->getUrl(true);

        if (str_starts_with($url, 'https://www.youtube.com/embed'))
            return true;

        if (str_starts_with($url, 'https://player.vimeo.com/video'))
            return true;

        return false;
    }

    public function getHostingThumbnailFromUrl($url)
    {
        if ($match = 'youtube.com/watch?v=' and ($pos = strpos($url, $match)) !== false) {
            $id = substr($url, $pos + strlen($match), 11);
            return 'https://img.youtube.com/vi/' . $id . '/maxresdefault.jpg';
        }

        return null;
    }

    public function prepareForEmbed($url)
    {
        if ($match = 'youtube.com/watch?v=' and ($pos = strpos($url, $match)) !== false) {
            $id = substr($url, $pos + strlen($match), 11);
            return 'https://www.youtube.com/embed/' . $id;
        }

        if ($match = 'https://vimeo.com/' and ($pos = strpos($url, $match)) !== false) {
            $id = (int) str_replace($match, '', $url);
            return 'https://player.vimeo.com/video/' . $id;
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

    /**
     * @param int $frames
     * @throws \Throwable
     */
    public function createFramesFromFile($frames = 4)
    {
        $videoPath = storage_path('app/public/' . $this->getPath());

        $ffmpeg = \FFMpeg\FFMpeg::create([
            'ffmpeg.binaries'  => config('video.ffmpeg_binaries'),
            'ffprobe.binaries' => config('video.ffprobe_binaries'),
            'timeout'          => 60,
            'ffmpeg.threads'   => 16,
        ]);
        /** @var \FFMpeg\Media\Video $ffVideo */
        $ffVideo = $ffmpeg->open($videoPath);

        $duration = $ffVideo->getFFProbe()->format($videoPath)->get('duration');
        $interval = ceil(($duration) / $frames);

        if ($duration <= 4)
            $interval = 1;

        for ($s = 1; $s < $duration - 1; $s += $interval) {
            $frame = $ffVideo->frame(\FFMpeg\Coordinate\TimeCode::fromSeconds($s));
            $frame->save($path = tempnam(sys_get_temp_dir(), 'laravel_app_'));

            $this->images()->add($path, true, 'image-' . $s . '.jpg');
        }
    }
}
