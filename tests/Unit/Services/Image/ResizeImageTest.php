<?php

namespace Tests\Unit\Services\Image;

use Tests\TestCase;
use Illuminate\Http\UploadedFile;
use App\Services\Image\ResizeImage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ResizeImageTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_resizes_an_image(): void
    {
        config(['filesystems.default' => 'images']);

        Storage::fake('images');
        //$file = UploadedFile::fake()->image('image.png');
        Storage::disk('images')->put('image.png', 'content');

        //$file = UploadedFile::fake()->image('image.png');

        $request = [
            'filename' => 'images/image.png',
            'extension' => 'png',
            'width' => '512',
        ];

        (new ResizeImage)->execute($request);

        //dd($file->hashName());
        Storage::disk('images')->assertExists('public/images/image-512.png');
    }
}
