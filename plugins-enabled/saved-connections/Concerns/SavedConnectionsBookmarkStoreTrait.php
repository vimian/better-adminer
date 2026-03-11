<?php

trait SavedConnectionsBookmarkStoreTrait
{
    private function normalizeBookmarks($bookmarks): array
    {
        if (!is_array($bookmarks)) {
            return array();
        }

        $normalized = array();
        foreach ($bookmarks as $bookmark) {
            if (!is_array($bookmark)) {
                continue;
            }

            $id = trim((string) ($bookmark['id'] ?? ''));
            $label = trim((string) ($bookmark['label'] ?? ''));
            $url = $this->normalizeBookmarkUrl((string) ($bookmark['url'] ?? ''), false);
            if ($id === '' || $label === '' || $url === null) {
                continue;
            }

            $normalized[] = array(
                'id' => $id,
                'label' => $label,
                'url' => $url,
                'createdAt' => (string) ($bookmark['createdAt'] ?? ''),
                'updatedAt' => (string) ($bookmark['updatedAt'] ?? ''),
            );
        }

        usort($normalized, static function (array $left, array $right): int {
            return strcasecmp((string) $left['label'], (string) $right['label']);
        });

        return $normalized;
    }

    private function upsertBookmark(array $bookmarks, array $bookmark, string $now): array
    {
        $label = trim((string) ($bookmark['label'] ?? ''));
        if ($label === '') {
            throw new RuntimeException('Bookmark name is required.', 422);
        }
        if (strlen($label) > 120) {
            $label = substr($label, 0, 120);
        }

        $url = $this->normalizeBookmarkUrl((string) ($bookmark['url'] ?? ''));
        $existingIndex = null;
        foreach ($bookmarks as $index => $existingBookmark) {
            if ((string) ($existingBookmark['url'] ?? '') === $url) {
                $existingIndex = $index;
                break;
            }
        }

        $record = array(
            'id' => $existingIndex === null ? bin2hex(random_bytes(12)) : $bookmarks[$existingIndex]['id'],
            'label' => $label,
            'url' => $url,
            'createdAt' => $existingIndex === null ? $now : $bookmarks[$existingIndex]['createdAt'],
            'updatedAt' => $now,
        );

        if ($existingIndex === null) {
            $bookmarks[] = $record;
        } else {
            $bookmarks[$existingIndex] = $record;
        }

        usort($bookmarks, static function (array $left, array $right): int {
            return strcasecmp((string) $left['label'], (string) $right['label']);
        });

        return array_values($bookmarks);
    }

    private function normalizeBookmarkUrl(string $url, bool $throwOnInvalid = true): ?string
    {
        $url = trim($url);
        $isValid = (
            $url !== '' &&
            !preg_match('~^[a-z][a-z0-9+.-]*:~i', $url) &&
            !str_starts_with($url, '//') &&
            (str_starts_with($url, '/') || str_starts_with($url, '?'))
        );

        if (!$isValid) {
            if ($throwOnInvalid) {
                throw new RuntimeException('Bookmark URL must stay within Adminer.', 422);
            }

            return null;
        }

        $parts = parse_url($url);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            if ($throwOnInvalid) {
                throw new RuntimeException('Bookmark URL must stay within Adminer.', 422);
            }

            return null;
        }

        return $url;
    }
}
