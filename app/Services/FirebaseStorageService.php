<?php

namespace App\Services;

use App\Exceptions;
use Kreait\Firebase\Factory;

class FirebaseStorageService
{
    protected $storage;

    public function __construct(Factory $factory)
    {
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $this->storage = $firebase->createStorage();
    }

    public function handle_uploads(array $files)
    {
        $bucket = $this->storage->getBucket(config(
            'firebase.projects.app.storage.default_bucket'
        ));

        foreach ($files as $file) {
            $name = str()->random().'.'.$file-> clientExtension();
            $bucket->upload(
                $file->get(),
                ['name' => $name]
            );
        }

    }
}
