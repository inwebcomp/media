<?php

namespace InWeb\Media\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use InWeb\Media\Image;
use InWeb\Media\Tests\ImageTestCase;
use InWeb\Media\Tests\TestEntity;

class ImageTest extends ImageTestCase
{
    use DatabaseMigrations;


    /** @test */
    public function can_create_image_from_file()
    {
        $file = UploadedFile::fake()->image('some image.jpg');
        $image = Image::new($file);

        $this->assertInstanceOf(Image::class, $image);
        $this->assertEquals($file, $image->getInstance());
    }

    /** @test */
    public function can_create_image_from_url()
    {
        $url = \Faker\Provider\Image::imageUrl();
        $image = Image::new($url);

        $this->assertInstanceOf(Image::class, $image);
        $this->assertEquals($url, $image->getInstance());
    }

    /** @test */
    public function can_normalize_image_name()
    {
        $image = $this->newImage('some image.jpg')->normalizeName();
        $this->assertEquals('some-image.jpg', $image->filename);

        $image = $this->newImage('some image(*).jpg')->normalizeName();
        $this->assertEquals('some-image.jpg', $image->filename);

        $image = $this->newImage('$some%image(*).jpg')->normalizeName();
        $this->assertEquals('someimage.jpg', $image->filename);

        $image = $this->newImage('some image.jpg', 'new image.jpg')->normalizeName();
        $this->assertEquals('new-image.jpg', $image->filename);
    }

    /** @test */
    public function can_get_unique_name()
    {
        $object = create(TestEntity::class);

        $image = $this->newImage('some-name.jpg');
        $image->associateWith($object)->save();

        $this->assertEquals('some-name-2.jpg', Image::getUniqueName('some-name.jpg', $image->model, $image->object->id));
    }

    /** @test */
    public function can_set_unique_name()
    {
        $object = create(TestEntity::class);

        $image = $this->newImage('some-name.jpg');
        $image->associateWith($object)->save();

        $image = $this->newImage('some-name.jpg');
        $image->associateWith($object);
        $image->setUniqueName();
        $image->save();

        $this->assertEquals('some-name-2.jpg', $image->filename);
        $this->assertEquals('some-name-2.jpg', $image->fresh()->filename);
    }

    /** @test */
    public function image_can_be_set_as_main()
    {
        $object = self::getObject();

        $image1 = $object->images()->add(self::createImage(), false);
        $image2 = $object->images()->add(self::createImage(), false);

        $image1->setMain();
        $this->assertTrue($image1->fresh()->isMain());
        $this->assertFalse($image2->fresh()->isMain());

        $image2->setMain();
        $this->assertFalse($image1->fresh()->isMain());
        $this->assertTrue($image2->fresh()->isMain());
    }

    /** @test */
    public function image_is_set_as_main_if_it_is_first_image_of_object()
    {
        $object = self::getObject();

        $image1 = $object->images()->add(self::createImage(), false);
        $image2 = $object->images()->add(self::createImage(), false);

        $this->assertTrue($image1->fresh()->isMain());
        $this->assertFalse($image2->fresh()->isMain());
    }
    
    /** @test */
    public function set_first_image_as_main_if_main_was_deleted()
    {
        $object = self::getObject();

        $image1 = $object->images()->add(self::createImage(), false);
        $image2 = $object->images()->add(self::createImage(), false);
        $image3 = $object->images()->add(self::createImage(), false);
        $image4 = $object->images()->add(self::createImage(), false);

        $image2->setMain();
        $this->assertFalse($image1->fresh()->isMain());
        $this->assertTrue($image2->fresh()->isMain());
        $this->assertFalse($image3->fresh()->isMain());
        $this->assertFalse($image4->fresh()->isMain());

        $image3->remove();
        $this->assertFalse($image1->fresh()->isMain());
        $this->assertTrue($image2->fresh()->isMain());
        $this->assertFalse($image4->fresh()->isMain());

        $image2->remove();
        $this->assertTrue($image1->fresh()->isMain());
        $this->assertFalse($image4->fresh()->isMain());
    }

    /** @test */
    public function recreate_thumbnails_when_main_image_is_changed()
    {
        $object = self::getObject();

        $image1 = $object->images()->add(self::createImage(), true); // is main
        $image2 = $object->images()->add(self::createImage(), true);

        // "test2" thumbnail is onlyForMainImage()
        $this->disk->assertExists($image1->getPath('test2'));
        $this->disk->assertMissing($image2->getPath('test2'));

        $image2->setMain();
        $this->disk->assertMissing($image1->getPath('test2'));
        $this->disk->assertExists($image2->getPath('test2'));
    }
}
