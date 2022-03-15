<?php

namespace InWeb\Media\Images;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InWeb\Base\Entity;
use InWeb\Media\Events\ImageAdded;
use InWeb\Media\Events\ImageRemoved;

class Images extends MorphMany
{
    /**
     * @var WithImages|Entity
     */
    protected $object;
    /**
     * @var WithImages|Entity
     */
    private $clonedObject;
    /**
     * @var string|null
     */
    public $type;

    /**
     * @param string|null $value
     * @return string|null
     */
    public function setType($value)
    {
        $this->type = $value;
        return $this;
    }

    /**
     * @return null|WithImages|Entity|Model
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * @param Entity|WithImages $object
     * @return self
     */
    public function setObject($object)
    {
        $this->object = $object;

        return $this;
    }

    public function getResults()
    {
        if (! $this->clonedObject)
            $this->clonedObject = clone $this->getObject();

        return parent::getResults()->map(function (Image $image) {
            $image->setRelation('object', $this->clonedObject);
            $image->setDisk($this->getDisk());
            return $image;
        });
    }

    /**
     * Match the eagerly loaded results to their many parents.
     *
     * @param  array $models
     * @param  Collection $results
     * @param  string $relation
     * @param  string $type
     * @return array
     */
    protected function matchOneOrMany(array $models, Collection $results, $relation, $type)
    {
        $dictionary = $this->buildDictionary($results);

        $disk = $this->getDisk();

        // Once we have the dictionary we can simply spin through the parent models to
        // link them up with their children using the keyed dictionary to make the
        // matching very convenient and easy work. Then we'll just return them.
        foreach ($models as $model) {
            if (isset($dictionary[$key = $model->getAttribute($this->localKey)])) {
                $model->setRelation(
                    $relation,
                    $this->getRelationValue($dictionary, $key, $type)->map(function (Image $image) use ($disk, $model) {
                        $image->setRelation('object', clone $model);
                        $image->setDisk($disk);
                        return $image;
                    })
                );
            }
        }

        return $models;
    }

    public function getDisk()
    {
        return $this->object->getDisk();
    }

    /**
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    public function getStorage()
    {
        return Storage::disk($this->getDisk());
    }

    /**
     * @param UploadedFile|string $image
     * @param bool $createThumbnails
     * @param null|string $filename
     * @return Image
     * @throws \Throwable
     */
    public function add($image, $createThumbnails = true, $filename = null)
    {
        $object = $this->getObject();

        $image = \DB::transaction(function () use ($object, $image, $createThumbnails, $filename) {
            $image = Image::new($image, $filename);
            $image->type = $this->type;
            $image->associateWith($object);
            $image->setDisk($this->getDisk());

            if ($object->imagesAutoName) {
                $autoName = $object->imageAutoName($image);
            } else {
                $autoName = false;
            }

            $image->normalizeName($autoName)->setUniqueName();

            $storage = $this->getStorage();

            $path = $image->getDir() . DIRECTORY_SEPARATOR . $image->filename;

            if ($object->getImageThumbnail('original')) {
                $image->createThumbnail('original');
            } else {
                $instance = $image->getInstance();

                if ($image->isBase64()) {
                    $storage->makeDirectory($image->getDir());
                    $storage->put($path, $image->getBase64DecodedContent());
                } else if (is_string($instance) and (
                        str_starts_with($instance, '//') or
                        str_starts_with($instance, 'http://') or
                        str_starts_with($instance, 'https://')
                    )) {
                    $storage->put($path, $this->getRemote($instance));
                } else if (is_string($instance)) {
                    $storage->put($path, file_get_contents($instance));
                } else {
                    $instance->storeAs($image->getDir(), $image->filename, $this->getDisk());
                }
            }

            $info = pathinfo($path);
            $image->format = $info['extension'] ?? null;

            if (! $object->fresh()->hasImagesOfType($image->type))
                $image->setMain();

            $image->save();

            if ($image->format) {
                foreach ($object->extraFormats() as $item) {
                    $format = $item['format'];
                    $quality = $item['quality'];
                    $image->createExtraFormatFile($format, $quality, 'original');
                }
            }

            if ($createThumbnails) {
                $image->createThumbnails();
            }

            event(new ImageAdded($image));

            return $image;
        });

        return $image;
    }

    /**
     * @param array $images
     * @param bool $createThumbnails
     * @return array
     * @throws \Throwable
     */
    public function addMany($images, $createThumbnails = true)
    {
        $result = [];

        foreach ($images as $image) {
            $result[] = $this->add($image, $createThumbnails);
        }

        return $result;
    }

    /**
     * @param $image
     * @return Image
     * @throws \Throwable
     */
    public function set($image)
    {
        $this->removeAll();

        return $this->add($image);
    }

    public function removeAll()
    {
        $this->getStorage()->deleteDirectory($this->object->getImagesDir());

        Image::where([
            'object_id' => $this->object->id,
            'model'     => get_class($this->object)
        ])->delete();

        $this->each(function ($image, $key) {
            $this->forget($key);
        });
    }

    public function remove($image)
    {
        $object = $this->getObject();

        if (! ($image instanceof Image)) {
            $image = $object->getImage($image);
        }

        $path = $image->getPath('original');

        if ($this->getStorage()->exists($path)) {
            if (! $this->getStorage()->delete($path)) {
                throw new \Exception("Can't delete image file");
            }
        }

        foreach ($object->extraFormats() as $format) {
            $path = $image->getPath('original', $format['format']);

            if ($this->getStorage()->exists($path)) {
                if (! $this->getStorage()->delete($path)) {
                    throw new \Exception("Can't delete image file");
                }
            }
        }

        $image->removeThumbnails();

        $wasMain = $image->fresh()->isMain();

        $image->delete();

        if ($wasMain and $first = $object->fresh()->images->first())
            $first->setMain();

        event(new ImageRemoved($image));

//        $this->reject(function ($item) use ($image) {
//            return $item->filename == $image->filename;
//        });
    }

    /**
     * @param string $name
     * @throws \Exception
     */
    public function removeThumbnail($name)
    {
        $this->assertThumbnailExists($name);

        $this->getStorage()->deleteDirectory($this->getObject()->getImageDir($name));
    }

    public function removeThumbnails()
    {
        $object = $this->getObject();

        foreach ($object->getImageThumbnails() as $name => $modifier) {
            $this->getStorage()->deleteDirectory($object->getImageDir($name));
        }
    }

    /**
     * @param string $name
     * @throws \Exception
     */
    public function recreateThumbnail($name)
    {
        $this->assertThumbnailExists($name);

        $this->each(function ($image) use ($name) {
            $image->recreateThumbnail($name);
        });
    }

    public function recreateThumbnails()
    {
        foreach ($this->getObject()->getImageThumbnails() as $name => $modifier) {
            $this->getResults()->each(function ($image) use ($name) {
                $image->recreateThumbnail($name);
            });
        }
    }

    /**
     * @param $name
     * @throws \Exception
     */
    public function assertThumbnailExists($name) : void
    {
        if (! $this->getObject()->imageThumbnailExists($name)) {
            throw new \Exception(
                'Thumbnail with name "' . $name . '" does not exists at model ' . $this->object->getMorphClass()
            );
        }
    }

    private function getRemote($url)
    {
        $file_headers = @get_headers($url);
        if (($file_headers[0] ?? false) == 'HTTP/1.1 404 Not Found') {
            throw new FileNotFoundException("Remote file does not exist at path {$url}");
        }

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_ENCODING, "");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_ENCODING, "");
//        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_HEADER, false);

        $data = curl_exec($ch);
        curl_close($ch);

        return $data;

//        return file_get_contents($url, false, stream_context_create([
//            "ssl" => [
//                "verify_peer"      => false,
//                "verify_peer_name" => false,
//            ],
//        ]));
    }

    /**
     * @return self
     */
    public function notMain()
    {
        return $this->where('main', '!=', '1');
    }

    public function getExtension($image)
    {

    }
}
