# InWeb Media

Media files manipulation

## Requirements
- Laravel >=5.7

## Installation

1. Install package via *composer require*
    ```
    composer require inweb/media
    ```
    or add to your composer.json to **require** section and update your dependencies
    ```
    "inweb/media": "*"
    ```
2. Run migrations
    ```
    php artisan migrate
    ```

You are ready to go!

## Usage
Add trait to your model (InWeb\Base\Entity)
```
use InWeb\Media\WithImages;
```
For thimbnails implement method:
```
use InWeb\Media\Thumbnail;

...

public function getImageThumbnails()
{
    return [
        'catalog' => new Thumbnail(function (\Intervention\Image\Image $image) {
            return $image->resize(100, 100, function (Constraint $c) {
                $c->aspectRatio();
                $c->upsize();
            })->resizeCanvas(100, 100);
        }, true),
    ];
}
```

Thumbnail class receives 2 parameters:
1. Closure with _\Intervention\Image\Image_ object
2. Boolean - Only for main image (default - **false**)

You can specify **original** thumbnail name to manipulate with original image.

```
public function getImageThumbnails()
{
    return [
        'original' => new Thumbnail(...),
    ];
}
```