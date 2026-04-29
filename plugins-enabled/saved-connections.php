<?php

require_once __DIR__.'/saved-connections/Concerns/SavedConnectionsAssetsTrait.php';
require_once __DIR__.'/saved-connections/Concerns/SavedConnectionsApiTrait.php';
require_once __DIR__.'/saved-connections/Concerns/SavedConnectionsAuthFormTrait.php';
require_once __DIR__.'/saved-connections/Concerns/SavedConnectionsConnectionStoreTrait.php';
require_once __DIR__.'/saved-connections/Concerns/SavedConnectionsCurrentConnectionTrait.php';
require_once __DIR__.'/saved-connections/Concerns/SavedConnectionsForeignKeyReferencesTrait.php';
require_once __DIR__.'/saved-connections/Concerns/SavedConnectionsSchemaGraphTrait.php';
require_once __DIR__.'/saved-connections/Concerns/SavedConnectionsStorageTrait.php';
require_once __DIR__.'/saved-connections/Concerns/SavedConnectionsBookmarkStoreTrait.php';
require_once __DIR__.'/saved-connections/SavedConnectionsPlugin.php';

return new SavedConnectionsPlugin();
