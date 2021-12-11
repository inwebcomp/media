<?php
if (! function_exists('create')) {
    function create($class, $attributes = [])
    {
        return \InWeb\Media\Database\Factories\TestEntityFactory::new()->create($attributes);
    }
}

if (! function_exists('make')) {
    function make($class, $attributes = [])
    {
        return \InWeb\Media\Database\Factories\TestEntityFactory::new($attributes);
    }
}
