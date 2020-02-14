<?php

namespace InWeb\Media\Tests;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use InWeb\Media\Images\Image;
use InWeb\Media\Tests\Unit\WithImagesTest;
use Storage;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class ImageTestCase extends TestCase
{
    use DatabaseMigrations;

    const IMAGE_SIZE = 10;

    /**
     * @var FilesystemAdapter
     */
    public $disk;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->disk = Storage::disk('public');
    }

    /**
     * @return TestEntity
     */
    public static function getObject()
    {
        return create(TestEntity::class);
    }

    /**
     * @param string $name
     * @return \Illuminate\Http\Testing\File
     */
    public static function createImage($name = null)
    {
        $name = $name ?: str_random(10);

        return UploadedFile::fake()->image($name, self::IMAGE_SIZE, self::IMAGE_SIZE);
    }

    /**
     * @param Image $image
     */
    public function assertImageExists(Image $image)
    {
        $this->disk->assertExists($image->getPath());

        $this->assertDatabaseHas('images', [
            'object_id' => $image->object->id,
            'model'     => $image->model,
            'filename'  => $image->filename
        ]);
    }

    /**
     * @param Image $image
     */
    public function assertImageIsValid(Image $image)
    {
        $path = $this->disk->path($image->getPath());

        $this->assertNotFalse(exif_imagetype($path));
    }

    /**
     * @param Image $image
     */
    public function assertImageMissing($image)
    {
        $this->disk->assertMissing($image->getPath());

        $this->assertDatabaseMissing('images', [
            'object_id' => $image->object_id,
            'model'     => $image->model,
            'filename'  => $image->filename
        ]);
    }

    /**
     * @param bool $createThumbnails
     * @return array
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public static function createAttachedImage($createThumbnails = false)
    {
        $object = WithImagesTest::getObject();

        $image = self::createImage();

        $image = $object->images()->add($image, $createThumbnails);

        return [$object, $image];
    }

    /**
     * @param string      $originalName
     * @param string|null $newName
     * @return Image
     */
    public function newImage($originalName = 'some image.jpg', $newName = null)
    {
        $file = UploadedFile::fake()->image($originalName);
        $image = Image::new($file, $newName);

        return $image;
    }
}
