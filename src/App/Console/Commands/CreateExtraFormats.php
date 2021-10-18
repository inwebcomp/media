<?php

namespace InWeb\Media\Console\Commands;

use Illuminate\Console\Command;
use InWeb\Base\Entity;
use InWeb\Media\Images\Image;
use InWeb\Media\Images\Images;
use InWeb\Media\Images\WithImages;

class CreateExtraFormats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:extra-formats';
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
        $images = Image::all();

        $this->getOutput()->progressStart(count($images));

        $images->each(function(Image $image) {
            $object = $image->getObject();

            foreach ($object->extraFormats() as $item) {
                $image->createExtraFormatFile($item['format'], $item['quality']);

                foreach ($object->getImageThumbnails() as $thumbnail => $info) {
                    $image->createExtraFormatFile($item['format'], $item['quality'], $thumbnail);
                }
            }

            $this->getOutput()->progressAdvance(1);
        });
    }
}
