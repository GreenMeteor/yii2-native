<?php

namespace greenmeteor\native\api;

class Filesystem extends BaseApi
{
    public static function apiName(): string
    {
        return 'Filesystem';
    }

    /** @return array{documents?:string,cache?:string,temp?:string} */
    public function getPaths(): array
    {
        return (array) ($this->invoke('getPaths') ?? []);
    }

    /**
     * Document picker. Result: `NativeEvents::FILES_PICKED`.
     *
     * @param string[] $types MIME types or UTIs
     * @return string request id
     */
    public function pickFiles(array $types = [], bool $multiple = false, ?string $id = null): string
    {
        $id = $this->requestId($id);
        $this->invoke('pickFiles', ['types' => array_values($types), 'multiple' => $multiple, 'id' => $id]);

        return $id;
    }

    public function saveToPhotos(string $path): void
    {
        $this->assertPath($path);
        $this->invoke('saveToPhotos', ['path' => $path]);
    }
}
