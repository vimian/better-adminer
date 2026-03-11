<?php

trait SavedConnectionsAssetsTrait
{
    public function head($darkMode = null)
    {
        foreach (array('saved-connections-base.css', 'saved-connections-modal.css') as $asset) {
            echo "<style>\n".$this->assetContents($asset)."\n</style>\n";
        }

        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $basePath = parse_url($requestUri, PHP_URL_PATH) ?: '/';
        $currentEndpoint = $requestUri !== '' ? $requestUri : $basePath;
        $config = array(
            'endpoint' => $basePath,
            'currentEndpoint' => $currentEndpoint,
            'apiParam' => self::API_PARAM,
            'token' => (string) ($_SESSION['token'] ?? ''),
        );

        echo Adminer\script(
            'window.AdminerSavedConnectionsConfig = '.
            json_encode(
                $config,
                JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            ).
            ';'
        );

        foreach (
            array(
                'saved-connections-runtime.js',
                'saved-connections-panels.js',
                'saved-connections-bookmarks.js',
                'saved-connections-form-actions.js',
                'saved-connections-current-page.js',
                'saved-connections-modal.js',
                'saved-connections-crypto.js',
            ) as $asset
        ) {
            echo Adminer\script($this->assetContents($asset));
        }

        return true;
    }
}
