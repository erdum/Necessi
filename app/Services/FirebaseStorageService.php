<?php

namespace App\Services;

class FirebaseStorageService
{
    protected $storage;
    protected $bucket;

    public function __construct()
    {
        $this->storage = app('firebase')->createStorage();

        $this->bucket = $this->storage->getBucket(
            config('firebase.projects.app.storage.default_bucket')
        );
    }

    public function upload_file(
        mixed $data,
        string $name,
        string $path
    ) {
        $object = $this->bucket->upload(
            $data,
            ['name' => $path.'/'.$name]
        );

        return $object->info()['mediaLink'];
    }

    public function handle_uploads(array $files)
    {
        $names = [];

        foreach ($files as $file) {
            $name = str()->random().'.'.$file->clientExtension();
            $names[] = $this->upload_file(
                $file->get(),
                $name,
                'chats-data'
            );
        }

        return $names;
    }
}
