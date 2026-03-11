<?php

class SavedConnectionsPlugin extends Adminer\Plugin
{
    use SavedConnectionsAssetsTrait;
    use SavedConnectionsApiTrait;
    use SavedConnectionsAuthFormTrait;
    use SavedConnectionsConnectionStoreTrait;
    use SavedConnectionsCurrentConnectionTrait;
    use SavedConnectionsStorageTrait;
    use SavedConnectionsBookmarkStoreTrait;

    private const API_PARAM = 'saved_connections_api';

    private string $storageFile;

    public function __construct(string $storageDir = '/var/lib/adminer')
    {
        $this->storageFile = rtrim($storageDir, '/').'/saved-connections.json';
    }

    private function assetContents(string $filename): string
    {
        $path = __DIR__.'/assets/'.$filename;
        $contents = file_get_contents($path);
        if (!is_string($contents)) {
            throw new RuntimeException('Unable to load saved connections assets.', 500);
        }

        return $contents;
    }
}
