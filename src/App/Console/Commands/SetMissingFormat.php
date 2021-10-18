<?php

namespace InWeb\Media\Console\Commands;

use Illuminate\Console\Command;
use InWeb\Base\Entity;
use InWeb\Media\Images\Image;
use InWeb\Media\Images\Images;
use InWeb\Media\Images\WithImages;

class SetMissingFormat extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:set-format';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set missing format value to all images';

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
        $images = Image::whereNull('format')->get();

        $this->getOutput()->progressStart(count($images));

        $images->each(function(Image $image) {
            $format = pathinfo($image->filename, PATHINFO_EXTENSION);
            $image->format = $format;
            $image->save();

            $this->getOutput()->progressAdvance(1);
        });
    }
}
