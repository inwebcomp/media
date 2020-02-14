<?php

namespace InWeb\Media\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use InWeb\Media\Images\Image;
use InWeb\Media\Images\Images;
use InWeb\Media\Images\Thumbnail;
use InWeb\Media\Tests\ImageTestCase;

class WithImagesTest extends ImageTestCase
{
    use DatabaseMigrations;

    /** @test */
    public function images_collection_can_be_obtained()
    {
        $object = self::getObject();

        $this->assertInstanceOf(Images::class, $object->images());
    }

    /** @test */
    public function images_collection_can_be_obtained_with_rigth_images()
    {
        $object = self::getObject();

        $image = ImagesTest::createImage();

        $object->images()->add($image);

        $this->assertEquals(1, $object->images()->count());

        $this->assertInstanceOf(Image::class, $object->images()->first());
    }

    /** @test */
    public function image_thumbnails_can_be_obtained()
    {
        $object = self::getObject();

        $this->assertTrue(is_array($object->getImageThumbnails()));
    }

    /** @test */
    public function image_thumbnail_can_be_obtained()
    {
        $object = self::getObject();

        $this->assertInstanceOf(Thumbnail::class, $object->getImageThumbnail('original'));
        $this->assertNull($object->getImageThumbnail('not_exist'));
    }

    /** @test */
    public function imageThumbnailExists_works_correctly()
    {
        $object = self::getObject();

        $this->assertTrue($object->imageThumbnailExists('test'));

        $this->assertFalse($object->imageThumbnailExists('not-exists'));
    }

    /** @test */
    public function removes_images_when_entity_is_deleted()
    {
        [$object, $image] = self::createAttachedImage();

        $this->assertImageExists($image);

        $imagePath = $image->getPath();

        $object->delete();

        $this->disk->assertMissing($imagePath);
    }
}
