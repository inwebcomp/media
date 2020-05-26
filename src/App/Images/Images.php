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
    private $clonedObject;

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

        // Once we have the dictionary we can simply spin through the parent models to
        // link them up with their children using the keyed dictionary to make the
        // matching very convenient and easy work. Then we'll just return them.
        foreach ($models as $model) {
            if (isset($dictionary[$key = $model->getAttribute($this->localKey)])) {
                $model->setRelation(
                    $relation,
                    $this->getRelationValue($dictionary, $key, $type)->map(function (Image $image) use ($model) {
                        $image->setRelation('object', clone $model);
                        return $image;
                    })
                );
            }
        }

        return $models;
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
            $image->associateWith($object);

            if ($object->imagesAutoName) {
                $autoName = $object->imageAutoName($image);
            } else {
                $autoName = false;
            }

            $image->normalizeName($autoName)->setUniqueName();

            if ($object->getImageThumbnail('original')) {
                $image->createThumbnail('original');
            } else {
                $path = $image->getDir() . DIRECTORY_SEPARATOR . $image->filename;
                if ($image->isBase64()) {
                    Storage::disk('public')->createDir($image->getDir());
                    Storage::disk('public')->put($path, base64_decode($image->getInstance()));
                } else if (is_string($image->getInstance())) {
                    Storage::disk('public')->put($path, $this->getRemote($image->getInstance()));
                } else {
                    $image->getInstance()->storeAs($image->getDir(), $image->filename, 'public');
                }
            }

            if (! $object->fresh()->hasImages())
                $image->main = 1;

            $image->save();

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
     * @throws FileNotFoundException
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
     * @throws FileNotFoundException
     */
    public function set($image)
    {
        $this->removeAll();

        return $this->add($image);
    }

    public function removeAll()
    {
        Storage::disk('public')->deleteDirectory($this->object->getImagesDir());

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

        Storage::disk('public')->delete($image->getPath());

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

        Storage::disk('public')->deleteDirectory($this->getObject()->getImageDir($name));
    }

    public function removeThumbnails()
    {
        $object = $this->getObject();

        foreach ($object->getImageThumbnails() as $name => $modifier) {
            Storage::disk('public')->deleteDirectory($object->getImageDir($name));
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
        if ($file_headers[0] == 'HTTP/1.1 404 Not Found') {
            throw new FileNotFoundException("Remote file does not exist at path {$url}");
        }

        return file_get_contents($url);
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
