<?php

namespace InWeb\Media\Console\Commands;

use Illuminate\Console\Command;
use InWeb\Base\Entity;
use InWeb\Media\Images\Image;
use InWeb\Media\Images\Images;
use InWeb\Media\Images\Thumbnail;
use InWeb\Media\Images\WithImages;

class CreateExtraFormats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:extra-formats {--format=} {--analyze}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create extra formats for all images';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $format = $this->option('format') ?: false;

        $images = Image::orderBy('model');

        if ($format) {
            $images->where('format', '=', $format);
        }

        $images = $images->get();

        $this->getOutput()->progressStart(count($images));

        $analyze = $this->option('analyze') ?: false;

        $originalSize = 0;
        $newSize = [];

        $images->each(function (Image $image) use (&$newSize, &$originalSize, $analyze) {
            $object = $image->getObject();
            $storage = $image->getStorage();

            try {
                foreach ($object->extraFormats() as $item) {
                    $format = $item['format'];
                    $quality = $item['quality'];

                    if (! $storage->exists($image->getPath()) or $image->format == 'svg') {
                        continue;
                    }

                    if ($analyze) {
                        $originalSize += $storage->size($image->getPath());
                    }

                    $image->createExtraFormatFile($format, $quality);

                    if ($analyze) {
                        if (! isset($newSize[$format]))
                            $newSize[$format] = 0;

                        $newSize[$format] += $storage->size($image->getPath('original', $format));
                    }

                    /** @var Thumbnail $thumbnail */
                    foreach ($object->getImageThumbnails() as $thumbnail => $info) {
                        if ($info->isOnlyForMain() and !$image->isMain())
                            continue;

                        $image->createExtraFormatFile($format, $quality, $thumbnail);
                    }
                }
            } catch (\Exception $exception) {
                $this->getOutput()->error($image->getPath());
                throw $exception;
            }

            $this->getOutput()->progressAdvance(1);
        });

        if ($analyze) {
            $this->getOutput()->newLine(2);

            foreach ($newSize as $format => $size) {
                $this->getOutput()->table(['Default', ucfirst($format)], [
                    [$this->formatBytes($originalSize, 2), $this->formatBytes($size, 2)]
                ]);

                $diff = $originalSize - $size;
                $this->getOutput()->success("Saved space (" . ucfirst($format) . "): " . $this->formatBytes($diff, 2) . ' (' . round($diff / ($originalSize / 100)) . '%)');
            }
        }
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        // Uncomment one of the following alternatives
//         $bytes /= pow(1024, $pow);
         $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
