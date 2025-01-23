<?php

namespace App\Jobs;

use App\Services\FirebaseStorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class StoreImages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data;

    protected $file_directory;

    protected $file_name;

    protected $disk_driver;

    /**
     * Create a new job instance.
     */
    public function __construct(
        $data,
        $file_directory,
        $file_name,
        $disk_driver = 'local'
    ) {
        $this->data = $data;
        $this->file_directory = $file_directory;
        $this->file_name = $file_name;
        $this->disk_driver = $disk_driver;
    }

    /**
     * Execute the job.
     */
    public function handle(FirebaseStorageService $storage)
    {
        $this->save_uploaded_image_to_webp(
            $this->data,
            $this->file_directory,
            $this->file_name,
            $this->disk_driver,
            $storage
        );
    }

    private function save_uploaded_image_to_webp(
        $image_data,
        $file_directory,
        $file_name,
        $disk_driver,
        FirebaseStorageService $storage
    ) {
        $image = Image::read($image_data);
        $converted_image = $image->toWebp(75)->toFilePointer();

        if ($disk_driver == 'firestorage') {
            return $storage->upload_file(
                $converted_image,
                $file_name.'.webp',
                $file_directory
            );
        } else {
            $file_path = "{$file_directory}/{$file_name}.webp";
            Storage::disk($disk_driver)->put($file_path, $converted_image);

            return $file_path;
        }
    }
}
