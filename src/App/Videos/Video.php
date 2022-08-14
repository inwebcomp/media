<?php

namespace InWeb\Media\Videos;

use Exception;
use FFMpeg\Format\FormatInterface;
use FFMpeg\Format\Video\WebM;
use FFMpeg\Format\Video\WMV;
use FFMpeg\Format\Video\X264;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
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
 * @property null|string format
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
    protected $disk    = 'public';

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

    public function pathInfo()
    {
        return pathinfo($this->getStorage()->path($this->getPath()));
    }

    public function setDisk($value)
    {
        $this->disk = $value;
        return $this;
    }

    public function getDisk()
    {
        return $this->disk;
    }

    /**
     * @return Filesystem
     */
    public function getStorage()
    {
        return Storage::disk($this->getDisk());
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

    public function getDir(?string $type = 'original') : string
    {
        return $this->object->getVideoDir($type);
    }

    public function getChunksDirectory() : string
    {
        return $this->getDir('_chunks');
    }

    public function getPath($type = 'original', $format = null) : string
    {
        return $this->getDir($type) . '/' . $this->getFormatFilename($format);
    }

    public function getUrl($preparedForEmbed = false, $type = 'original', $format = null)
    {
        if ($url = $this->getRawOriginal('url'))
            return $preparedForEmbed ? $this->prepareForEmbed($url) : $url;

        return static::url($this->getPath($type, $format));
    }

    public function getFormatFilename($format = null) : string|null
    {
        if (! $format)
            return $this->filename;

        $originalFormat = $this->format;

        if (! $originalFormat) {
            $info = $this->pathInfo();
            return $info['filename'] . '.' . $format;
        }

        return preg_replace('/\.' . $originalFormat . '$/', '.' . $format, $this->filename);
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
        return ! ($this->attributes['url'] ?? false);
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

    public function getMimeTypeAttribute($type = 'original', $format = null) : string|false|null
    {
        if ($this->isLocal())
            return $this->getStorage()->mimeType($this->getPath($type, $format));

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

    public static function createFFMpegResolver() : \FFMpeg\FFMpeg
    {
        static $resolver = null;

        if ($resolver) {
            return $resolver;
        }

        return $resolver = \FFMpeg\FFMpeg::create([
            'ffmpeg.binaries'  => config('video.ffmpeg_binaries'),
            'ffprobe.binaries' => config('video.ffprobe_binaries'),
            'timeout'          => 60,
            'ffmpeg.threads'   => 16,
        ]);
    }

    public function getFullPath($type = 'original', $format = null)
    {
        return $this->getStorage()->path($this->getPath($type, $format));
    }

    /**
     * @param int $frames
     * @param float $startFromTime
     * @throws \Throwable
     */
    public function createFramesFromFile($frames = 4, $startFromTime = 1)
    {
        $ffmpeg = static::createFFMpegResolver();

        $videoPath = $this->getFullPath();

        /** @var \FFMpeg\Media\Video $ffVideo */
        $ffVideo = $ffmpeg->open($videoPath);

        $duration = $ffVideo->getFFProbe()->format($videoPath)->get('duration');
        $interval = ceil(($duration) / $frames);

        if ($duration <= 4)
            $interval = 1;

        for ($s = $startFromTime; $s < $duration - 1; $s += $interval) {
            $frame = $ffVideo->frame(\FFMpeg\Coordinate\TimeCode::fromSeconds($s));
            $frame->save($path = tempnam(sys_get_temp_dir(), 'laravel_app_'));

            $this->images()->add($path, true, 'image-' . $s . '.jpg');
        }
    }

    /**
     * @param float $fromSeconds
     * @throws \Throwable
     */
    public function getFrame($fromSeconds = 0)
    {
        $ffmpeg = static::createFFMpegResolver();

        $videoPath = $this->getFullPath();

        /** @var \FFMpeg\Media\Video $ffVideo */
        $ffVideo = $ffmpeg->open($videoPath);

        $frame = $ffVideo->frame(\FFMpeg\Coordinate\TimeCode::fromSeconds($fromSeconds));
        $frame->save($path = tempnam(sys_get_temp_dir(), 'laravel_app_'));

        return $path;
    }

    public function createExtraFormatFile(ExtraFormat $format, string $type = 'original')
    {
        $source = $this->getFullPath($type);

        $info = pathinfo($source);

        if (! isset($info['extension']))
            return;

        $ffmpeg = static::createFFMpegResolver();

        $video = $ffmpeg->open($source);

        $format->handle($video, $this);

        $video->save($format->getFFmpegFormat(), $this->getFullPath($type, $format->getExtension()));
    }

    /**
     * @param string $name
     * @param \Closure|null $modifier
     * @return false|\FFMpeg\Media\Video
     * @throws Exception
     */
    public function createVariant(string $name, \Closure $modifier = null) : bool|\FFMpeg\Media\Video
    {
        $this->assertVariantExists($name);

        /** @var WithVideos|Entity $object */
        $object = $this->object;
        $variantDefinition = $object->getVideoVariant($name);

        if (! $variantDefinition->shouldCreateVariant($object, $this))
            return false;

        if ($variantDefinition->isOnlyForMain() and ! $this->isMain())
            return false;

        $function = $modifier ?? $variantDefinition->getModifier();

        $storage = $this->getStorage();

        $storage->makeDirectory($this->getDir($name));

        $source = $this->getFullPath();

        $info = pathinfo($source);

        $ffmpeg = static::createFFMpegResolver();

        $video = $ffmpeg->open($source);

        $path = $this->getFullPath($name);

        $function($video, $this);

        $format = $this->getFFmpegFormatFromExtension($info['extension']);

        $formatFunction = $variantDefinition->getFormatModifier();

        if ($formatFunction) {
            $format = $formatFunction($format);
        }

        $video->save($format, $path);

        if (count($object->videoExtraFormats())) {
            foreach ($object->videoExtraFormats() as $format) {
                if (($info['extension'] ?? null) == $format->getExtension())
                    continue;

                $this->createExtraFormatFile($format, $name);
            }
        }

        return $video;
    }

    public function getFFmpegFormatFromExtension($extension) : FormatInterface
    {
        return match ($extension) {
            'webm' => new WebM(),
            'mp4' => new X264(),
            'wmv' => new WMV(),
        };
    }

    /**
     * @throws Exception
     */
    public function createVariants()
    {
        foreach ($this->object->getVideoVariants() as $name => $variant) {
            $this->createVariant($name);
        }
    }

    /**
     * @throws Exception
     */
    public function createMainVariants()
    {
        foreach ($this->object->getVideoVariants() as $name => $variant) {
            if ($variant->isOnlyForMain())
                $this->createVariant($name);
        }
    }

    /**
     * @param $name
     * @return bool
     * @throws Exception
     */
    public function removeVariant($name) : bool
    {
        $this->assertVariantExists($name);

        $path = $this->getPath($name);
        if ($this->getStorage()->exists($path)) {
            if (! $this->getStorage()->delete($path)) {
                throw new Exception("Can't delete image file");
            }
        }

        foreach ($this->object->videoExtraFormats() as $format) {
            $path = $this->getPath($name, $format->getExtension());
            if ($this->getStorage()->exists($path)) {
                if (! $this->getStorage()->delete($path)) {
                    throw new Exception("Can't delete image file");
                }
            }
        }

        return true;
    }

    /**
     * @throws Exception
     */
    public function removeVariants() : void
    {
        foreach ($this->object->getVideoVariants() as $name => $variant) {
            $this->removeVariant($name);
        }
    }

    /**
     * @throws Exception
     */
    public function removeMainVariants() : void
    {
        foreach ($this->object->getVideoVariants() as $name => $variant) {
            if ($variant->isOnlyForMain())
                $this->removeVariant($name);
        }
    }

    /**
     * @param $name
     * @return bool|\FFMpeg\Media\Video
     * @throws Exception
     */
    public function recreateVariant($name) : \FFMpeg\Media\Video|bool
    {
        $this->assertVariantExists($name);

        return $this->createVariant($name);
    }

    /**
     * @param $name
     * @throws Exception
     */
    public function assertVariantExists($name) : void
    {
        if (! $this->object->videoVariantExists($name)) {
            throw new Exception(
                'Video variant with name "' . $name . '" does not exists at model ' . $this->object->getMorphClass()
            );
        }
    }
}
