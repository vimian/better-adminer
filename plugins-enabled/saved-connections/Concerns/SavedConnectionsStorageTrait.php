<?php

trait SavedConnectionsStorageTrait
{
    private function loadStore(): array
    {
        $this->ensureStorageDirectory();
        if (!is_file($this->storageFile)) {
            return array('version' => 2, 'connections' => array());
        }

        $json = file_get_contents($this->storageFile);
        $decoded = json_decode($json ?: '', true);
        if (!is_array($decoded) || !isset($decoded['connections']) || !is_array($decoded['connections'])) {
            return array('version' => 2, 'connections' => array());
        }

        $connections = array();
        foreach ($decoded['connections'] as $connection) {
            if (!is_array($connection)) {
                continue;
            }

            $id = trim((string) ($connection['id'] ?? ''));
            $label = trim((string) ($connection['label'] ?? ''));
            $payload = $connection['payload'] ?? null;
            if ($id === '' || $label === '' || !is_array($payload)) {
                continue;
            }

            $connections[] = array(
                'id' => $id,
                'label' => $label,
                'fingerprint' => $this->normalizeFingerprint((string) ($connection['fingerprint'] ?? '')),
                'payload' => $payload,
                'bookmarks' => $this->normalizeBookmarks($connection['bookmarks'] ?? array()),
                'createdAt' => (string) ($connection['createdAt'] ?? ''),
                'updatedAt' => (string) ($connection['updatedAt'] ?? ''),
            );
        }

        usort($connections, static function (array $left, array $right): int {
            return strcasecmp((string) $left['label'], (string) $right['label']);
        });

        return array('version' => 2, 'connections' => $connections);
    }

    private function writeStore(array $store): void
    {
        $this->ensureStorageDirectory();
        $store['version'] = 2;

        $encoded = json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode connection store.', 500);
        }

        $tempFile = $this->storageFile.'.tmp';
        if (file_put_contents($tempFile, $encoded, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write connection store.', 500);
        }
        if (!rename($tempFile, $this->storageFile)) {
            throw new RuntimeException('Unable to finalize connection store.', 500);
        }
    }

    private function ensureStorageDirectory(): void
    {
        $directory = dirname($this->storageFile);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to initialize connection storage.', 500);
        }
    }

    private function normalizeFingerprint(string $fingerprint): string
    {
        $fingerprint = strtolower(trim($fingerprint));
        return preg_match('/^[a-f0-9]{64}$/', $fingerprint) === 1 ? $fingerprint : '';
    }

    private function findConnectionIndexByFingerprint(array $connections, string $fingerprint): ?int
    {
        foreach ($connections as $index => $connection) {
            if ((string) ($connection['fingerprint'] ?? '') === $fingerprint) {
                return $index;
            }
        }

        return null;
    }

    private function findConnectionIndexById(array $connections, string $id): ?int
    {
        foreach ($connections as $index => $connection) {
            if ((string) ($connection['id'] ?? '') === $id) {
                return $index;
            }
        }

        return null;
    }
}
