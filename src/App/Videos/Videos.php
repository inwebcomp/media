<?php

namespace InWeb\Media\Videos;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InWeb\Base\Entity;
use InWeb\Base\Exceptions\ModelIsNotBinded;
use InWeb\Media\Events\VideoAdded;
use InWeb\Media\Events\VideoRemoved;

class Videos extends MorphMany
{
    /**
     * @var WithVideos|Entity
     */
    protected $object;

    private $clonedObject;

    /**
     * @return null|WithVideos|Entity|Model
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * @param Entity|WithVideos $object
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

        return parent::getResults()->map(function ($video) {
            $video->setRelation('object', $this->clonedObject);
            return $video;
        });
    }

    /**
     * Match the eagerly loaded results to their many parents.
     *
     * @param array $models
     * @param Collection $results
     * @param string $relation
     * @param string $type
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
                    $this->getRelationValue($dictionary, $key, $type)->map(function ($video) use ($model) {
                        $video->setRelation('object', clone $model);
                        return $video;
                    })
                );
            }
        }

        return $models;
    }

    /**
     * @param UploadedFile|string $video
     * @param null|string $filename
     * @return Video
     * @throws FileNotFoundException
     */
    public function add($video, $filename = null)
    {
        $object = $this->getObject();

        $video = call_user_func([$object->videoModel(), 'new'], $video, $filename);
        $video->associateWith($object);

        $video->normalizeName()->setUniqueName();

        $path = $video->getDir() . DIRECTORY_SEPARATOR . $video->filename;

        if ($video->isBase64()) {
            Storage::disk('public')->createDir($video->getDir());
            Storage::disk('public')->put($path, base64_decode($video->getInstance()));
        } else if (is_string($video->getInstance()) && strpos($video->getInstance(), 'http') === 0) {
            $video->url = $video->getInstance();
            $video->filename = null;
        } else if (is_string($video->getInstance())) {
            Storage::disk('public')->put($path, $this->getRemote($video->getInstance()));
        } else {
            $video->getInstance()->storeAs($video->getDir(), $video->filename, 'public');
        }

        if (! $object->fresh()->hasVideos())
            $video->main = 1;

        $video->save();

        event(new VideoAdded($video));

        return $video;
    }

    /**
     * @param array $videos
     * @return array
     * @throws FileNotFoundException
     */
    public function addMany($videos)
    {
        $result = [];

        foreach ($videos as $video) {
            $result[] = $this->add($video);
        }

        return $result;
    }

    /**
     * @param $video
     * @return Video
     * @throws FileNotFoundException
     */
    public function set($video)
    {
        $this->removeAll();

        return $this->add($video);
    }

    public function removeAll()
    {
        Storage::disk('public')->deleteDirectory($this->object->getVideosDir());

        Video::where([
            'object_id' => $this->object->id,
            'model'     => get_class($this->object)
        ])->delete();

        $this->each(function ($video, $key) {
            $this->forget($key);
        });
    }

    public function remove($video)
    {
        $object = $this->getObject();

        if (! ($video instanceof Video)) {
            $video = $object->getVideo($video);
        }

        Storage::disk('public')->delete($video->getPath());

        $video->delete();

        event(new VideoRemoved($video));
    }

    private function getRemote($url)
    {
        $file_headers = @get_headers($url);
        if ($file_headers[0] == 'HTTP/1.1 404 Not Found') {
            throw new FileNotFoundException("Remote file does not exist at path {$url}");
        }

        return file_get_contents($url);
    }
}
