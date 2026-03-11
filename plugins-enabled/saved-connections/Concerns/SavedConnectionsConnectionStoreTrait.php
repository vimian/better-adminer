<?php

trait SavedConnectionsConnectionStoreTrait
{
    private function listConnections(): array
    {
        $store = $this->loadStore();
        return array_values($store['connections']);
    }

    private function saveConnection(array $request): array
    {
        $label = trim((string) ($request['label'] ?? ''));
        if ($label === '') {
            throw new RuntimeException('Connection name is required.', 422);
        }
        if (strlen($label) > 120) {
            $label = substr($label, 0, 120);
        }

        $payload = $request['payload'] ?? null;
        if (!is_array($payload)) {
            throw new RuntimeException('Encrypted payload is required.', 422);
        }
        foreach (array('salt', 'iv', 'ciphertext', 'iterations') as $requiredKey) {
            if (!array_key_exists($requiredKey, $payload)) {
                throw new RuntimeException('Encrypted payload is incomplete.', 422);
            }
        }

        $fingerprint = $this->normalizeFingerprint((string) ($request['fingerprint'] ?? ''));
        if ($fingerprint === '') {
            throw new RuntimeException('Connection fingerprint is required.', 422);
        }

        $store = $this->loadStore();
        $now = gmdate(DATE_ATOM);
        $existingIndex = null;
        foreach ($store['connections'] as $index => $connection) {
            if (
                (string) ($connection['fingerprint'] ?? '') === $fingerprint ||
                strcasecmp((string) $connection['label'], $label) === 0
            ) {
                $existingIndex = $index;
                break;
            }
        }

        $record = array(
            'id' => $existingIndex === null ? bin2hex(random_bytes(12)) : $store['connections'][$existingIndex]['id'],
            'label' => $label,
            'fingerprint' => $fingerprint,
            'payload' => $payload,
            'bookmarks' => $existingIndex === null
                ? array()
                : $this->normalizeBookmarks($store['connections'][$existingIndex]['bookmarks'] ?? array()),
            'createdAt' => $existingIndex === null ? $now : $store['connections'][$existingIndex]['createdAt'],
            'updatedAt' => $now,
        );

        if (is_array($request['bookmark'] ?? null)) {
            $record['bookmarks'] = $this->upsertBookmark($record['bookmarks'], $request['bookmark'], $now);
        }

        if ($existingIndex === null) {
            $store['connections'][] = $record;
        } else {
            $store['connections'][$existingIndex] = $record;
        }

        usort($store['connections'], static function (array $left, array $right): int {
            return strcasecmp((string) $left['label'], (string) $right['label']);
        });

        $this->writeStore($store);

        return $record;
    }

    private function saveBookmark(array $request): array
    {
        $fingerprint = $this->normalizeFingerprint((string) ($request['fingerprint'] ?? ''));
        if ($fingerprint === '') {
            throw new RuntimeException('Connection fingerprint is required.', 422);
        }

        $bookmark = $request['bookmark'] ?? null;
        if (!is_array($bookmark)) {
            throw new RuntimeException('Bookmark data is required.', 422);
        }

        $store = $this->loadStore();
        $index = $this->findConnectionIndexByFingerprint($store['connections'], $fingerprint);
        if ($index === null) {
            throw new RuntimeException('Save this connection before bookmarking pages.', 409);
        }

        $now = gmdate(DATE_ATOM);
        $record = $store['connections'][$index];
        $record['bookmarks'] = $this->upsertBookmark(
            $this->normalizeBookmarks($record['bookmarks'] ?? array()),
            $bookmark,
            $now
        );
        $record['updatedAt'] = $now;
        $store['connections'][$index] = $record;
        $this->writeStore($store);

        return $record;
    }

    private function deleteConnection(array $request): void
    {
        $id = trim((string) ($request['id'] ?? ''));
        if ($id === '') {
            throw new RuntimeException('Connection id is required.', 422);
        }

        $store = $this->loadStore();
        $store['connections'] = array_values(array_filter(
            $store['connections'],
            static fn (array $connection): bool => (string) ($connection['id'] ?? '') !== $id
        ));
        $this->writeStore($store);
    }

    private function deleteBookmark(array $request): array
    {
        $connectionId = trim((string) ($request['connectionId'] ?? ''));
        $bookmarkId = trim((string) ($request['bookmarkId'] ?? ''));
        if ($connectionId === '' || $bookmarkId === '') {
            throw new RuntimeException('Connection and bookmark ids are required.', 422);
        }

        $store = $this->loadStore();
        $connectionIndex = $this->findConnectionIndexById($store['connections'], $connectionId);
        if ($connectionIndex === null) {
            throw new RuntimeException('Saved connection not found.', 404);
        }

        $record = $store['connections'][$connectionIndex];
        $record['bookmarks'] = array_values(array_filter(
            $this->normalizeBookmarks($record['bookmarks'] ?? array()),
            static fn (array $bookmark): bool => (string) ($bookmark['id'] ?? '') !== $bookmarkId
        ));
        $record['updatedAt'] = gmdate(DATE_ATOM);
        $store['connections'][$connectionIndex] = $record;
        $this->writeStore($store);

        return $record;
    }

    private function linkConnection(array $request): array
    {
        $id = trim((string) ($request['id'] ?? ''));
        $fingerprint = $this->normalizeFingerprint((string) ($request['fingerprint'] ?? ''));
        if ($id === '' || $fingerprint === '') {
            throw new RuntimeException('Connection id and fingerprint are required.', 422);
        }

        $store = $this->loadStore();
        $index = $this->findConnectionIndexById($store['connections'], $id);
        if ($index === null) {
            throw new RuntimeException('Saved connection not found.', 404);
        }

        $record = $store['connections'][$index];
        $record['fingerprint'] = $fingerprint;
        $store['connections'][$index] = $record;
        $this->writeStore($store);

        return $record;
    }
}
