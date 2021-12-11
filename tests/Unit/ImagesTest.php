<?php

namespace InWeb\Media\Tests\Unit;

use Event;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use InWeb\Media\Events\ImageAdded;
use InWeb\Media\Events\ImageRemoved;
use InWeb\Media\Tests\ImageTestCase;

class ImagesTest extends ImageTestCase
{
    use DatabaseMigrations;

    /** @test */
    public function image_can_be_added()
    {
        [, $image] = self::createAttachedImage();

        $this->assertImageExists($image);
    }

    /** @test */
    public function can_add_image_from_url()
    {
        $object = WithImagesTest::getObject();

        $image = \Faker\Provider\Image::imageUrl();

        $image = $object->images()->add($image, false, 'image.jpg');

        $this->assertImageExists($image);
        $this->assertEquals($image->filename, 'image.jpg');
    }

    /** @test */
    public function can_add_image_from_base64()
    {
        $object = WithImagesTest::getObject();

        $image = self::createImage('image.jpg');
        $base64 = 'data:' . \File::mimeType($image) . ';base64,' . base64_encode(\File::get($image));

        $image = $object->images()->add($base64, false, 'image.jpg');
        $image = $object->fresh()->images()->first();

        $this->assertImageExists($image);
        $this->assertEquals($image->filename, 'image.jpg');
        $this->assertImageIsValid($image);
    }

    /** @test */
    public function svg_image_can_be_added()
    {
        $object = WithImagesTest::getObject();

        $image = __DIR__ . '/../resources/image.svg';

        $image = $object->images()->add($image, false);

        $this->assertImageExists($image);
    }

    public function test_images_can_be_added()
    {
        $object = WithImagesTest::getObject();

        $image = self::createImage();
        $image2 = self::createImage();

        [$image, $image2] = $object->images()->addMany([
            $image,
            $image2
        ]);

        $this->assertImageExists($image);
        $this->assertImageExists($image2);
    }

    public function test_image_can_be_set()
    {
        [$object, $image] = self::createAttachedImage();

        $this->assertImageExists($image);

        $image2 = self::createImage();

        $image2 = $object->images()->set($image2);

        $this->assertImageMissing($image);
        $this->assertImageExists($image2);
    }

    /** @test */
    public function images_with_equival_names_can_be_added()
    {
        $object = WithImagesTest::getObject();

        $image = self::createImage('image.png');
        $image2 = self::createImage('image.png');

        $image = $object->images()->add($image);
        $image2 = $object->images()->add($image2);

        $this->assertImageExists($image);
        $this->assertImageExists($image2);
    }

    /** @test */
    public function images_with_equival_names_get_postfix()
    {
        $object = self::getObject();
        $otherObject = self::getObject();

        $otherImage = self::createImage('image.png');
        $otherImage = $otherObject->images()->add($otherImage);

        $image = self::createImage('image.png');
        $image2 = self::createImage('image.png');

        $image = $object->images()->add($image);
        $image2 = $object->images()->add($image2);

        $this->assertEquals('image.png', $image->fresh()->filename);
        $this->assertEquals('image-2.png', $image2->fresh()->filename);
    }

    public function test_image_can_be_removed_by_Image_object()
    {
        [$object, $image] = self::createAttachedImage();

        $this->assertImageExists($image);

        $object->images()->remove($image);

        $this->assertImageMissing($image);
    }

    public function test_image_can_be_removed_by_Image_id()
    {
        [$object, $image] = self::createAttachedImage();

        $this->assertImageExists($image);

        $object->images()->remove($image->id);

        $this->assertImageMissing($image);
    }

    public function test_image_can_be_removed_by_image_filename()
    {
        [$object, $image] = self::createAttachedImage();

        $this->assertImageExists($image);

        $object->images()->remove($image->filename);

        $this->assertImageMissing($image);
    }

    /** @test */
    public function create_thumbnail()
    {
        [, $image] = self::createAttachedImage();

        $image->createThumbnail('test');

        $this->disk->assertExists($image->getPath('test'));
    }

    /** @test */
    public function create_thumbnail_from_closure()
    {
        [, $image] = self::createAttachedImage();

        $image->createThumbnail('test', function (\Intervention\Image\Image $image) {
            return $image->resize(30, 30);
        });

        $this->disk->assertExists($image->getPath('test'));

        [$width, $height] = getimagesize($this->disk->path($image->getPath('test')));

        $this->assertEquals(30, $width);
        $this->assertEquals(30, $height);
    }

    /** @test */
    public function can_not_create_thumbnail_with_not_existing_name()
    {
        [, $image] = self::createAttachedImage();

        $this->expectException(\Exception::class);

        $image->createThumbnail('not-existing-name');
    }

    /** @test */
    public function can_not_create_thumbnail_with_not_existing_name_from_closure()
    {
        [, $image] = self::createAttachedImage();

        $this->expectException(\Exception::class);

        $image->createThumbnail('not-existing-name', function (\Intervention\Image\Image $image) {
            return $image;
        });
    }

    /** @test */
    public function dont_create_thumbnail_if_it_is_only_for_main_image()
    {
        $object = self::getObject();
        $imageMain = $object->images()->add(self::createImage(), true);
        $imageNotMain = $object->images()->add(self::createImage(), true);

        // "test2" thumbnail is onlyForMainImage()
        $this->disk->assertExists($imageMain->getPath('test2'));
        $this->disk->assertMissing($imageNotMain->getPath('test2'));
    }

    /** @test */
    public function adding_an_image_goes_through_original_thumbnail()
    {
        // Width and height of that image is 10px
        // TestEntity has image type "original", that resize image to 20x20px
        [, $image] = self::createAttachedImage(true);

        [$width, $height] = getimagesize($this->disk->path($image->getPath()));

        $this->assertEquals(20, $width);
        $this->assertEquals(20, $height);
    }

    /** @test */
    public function createThumbnails_can_create_all_thumbnails()
    {
        [, $image] = self::createAttachedImage();

        $image->createThumbnails();

        // Original thumbnail
        [$width, $height] = getimagesize($this->disk->path($image->getPath()));
        $this->assertEquals(20, $width);
        $this->assertEquals(20, $height);

        // Test thumbnail
        $this->disk->assertExists($image->getPath('test'));
    }

    /** @test */
    public function when_adding_image_also_creates_thumbnails()
    {
        [, $image] = self::createAttachedImage(true);

        $this->disk->assertExists($image->getPath('test'));
    }

    /** @test */
    public function can_remove_thumbnail()
    {
        [, $image] = self::createAttachedImage(true);

        $image->removeThumbnail('test');

        $this->disk->assertMissing($image->getPath('test'));
    }

    /** @test */
    public function can_not_remove_not_existing_thumbnail()
    {
        [, $image] = self::createAttachedImage(true);

        $this->expectException(\Exception::class);

        $image->removeThumbnail('not-exists');
    }

    /** @test */
    public function can_remove_thumbnail_of_entity()
    {
        [$object, $image] = self::createAttachedImage(true);

        $object->images()->removeThumbnail('test');

        $this->disk->assertMissing($image->getPath('test'));
    }

    /** @test */
    public function can_remove_all_thumbnails_of_entity()
    {
        [$object, $image] = self::createAttachedImage(true);

        $object->images()->removeThumbnails();

        $this->disk->assertMissing($image->getPath('test'));
        $this->disk->assertMissing($image->getPath('test2'));
    }

    /** @test */
    public function when_removing_all_entity_images_also_removes_thumbnails()
    {
        [$object, $image] = self::createAttachedImage(true);

        $object->images()->removeAll($image);

        $this->disk->assertMissing($image->getPath('test'));
    }

    /** @test */
    public function when_removing_image_also_removes_thumbnails()
    {
        [$object, $image] = self::createAttachedImage(true);

        $object->images()->remove($image);

        $this->disk->assertMissing($image->getPath('test'));
    }

    /** @test */
    public function recreate_thumbnail_of_image()
    {
        [, $image] = self::createAttachedImage();

        $image->createThumbnail('test', function (\Intervention\Image\Image $image) {
            return $image->resize(30, 30);
        });

        [$width, $height] = getimagesize($this->disk->path($image->getPath('test')));

        $this->assertEquals(30, $width);
        $this->assertEquals(30, $height);

        $image->recreateThumbnail('test');

        [$width, $height] = getimagesize($this->disk->path($image->getPath('test')));

        $this->assertEquals(100, $width);
        $this->assertEquals(100, $height);

        // Can't recreate not existing thumbnail
        $this->expectException(\Exception::class);
        $image->recreateThumbnail('not-existing-thumbnail');
    }

    /** @test */
    public function can_not_recreate_not_existing_thumbnail()
    {
        [, $image] = self::createAttachedImage(true);

        $this->expectException(\Exception::class);

        $image->removeThumbnail('not-existing');
    }

    /** @test */
    public function recreate_thumbnail_of_entity()
    {
        [$object, $image] = self::createAttachedImage();

        $image->createThumbnail('test', function (\Intervention\Image\Image $image) {
            return $image->resize(30, 30);
        });

        [$width, $height] = getimagesize($this->disk->path($image->getPath('test')));

        $this->assertEquals(30, $width);
        $this->assertEquals(30, $height);

        $object->images()->recreateThumbnail('test');

        [$width, $height] = getimagesize($this->disk->path($image->getPath('test')));

        $this->assertEquals(100, $width);
        $this->assertEquals(100, $height);
    }

    /** @test */
    public function recreate_all_thumbnails_of_entity()
    {
        [$object, $image] = self::createAttachedImage();

        $image->createThumbnail('test', function (\Intervention\Image\Image $image) {
            return $image->resize(30, 30);
        });
        $image->createThumbnail('test2', function (\Intervention\Image\Image $image) {
            return $image->resize(30, 30);
        });

        [$width, $height] = getimagesize($this->disk->path($image->getPath('test')));

        $this->assertEquals(30, $width);
        $this->assertEquals(30, $height);

        $object->images()->recreateThumbnails();

        [$width, $height] = getimagesize($this->disk->path($image->getPath('test'))); // 100x100px

        $this->assertEquals(100, $width);
        $this->assertEquals(100, $height);

        [$width, $height] = getimagesize($this->disk->path($image->getPath('test2'))); // 15x15px

        $this->assertEquals(15, $width);
        $this->assertEquals(15, $height);
    }

    /** @test */
    public function event_is_dispatched_after_adding_an_image()
    {
        Event::fake();

        [, $image] = self::createAttachedImage();

        Event::assertDispatched(ImageAdded::class, function ($e) use ($image) {
            return $e->image->id === $image->id;
        });
    }

    /** @test */
    public function event_is_dispatched_after_removing_an_image()
    {
        Event::fake();

        [, $image] = self::createAttachedImage();

        $image->remove();

        Event::assertDispatched(ImageRemoved::class, function ($e) use ($image) {
            return $e->image->id === $image->id;
        });
    }
}
