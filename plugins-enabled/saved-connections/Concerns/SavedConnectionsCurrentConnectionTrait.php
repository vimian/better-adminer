<?php

trait SavedConnectionsCurrentConnectionTrait
{
    private function getCurrentConnection(): array
    {
        $username = $this->getCurrentUsername();
        $password = Adminer\get_password();
        if ((!is_string($password) || $password === '') && $username !== '') {
            $password = $this->getStoredPassword($username);
        }

        if (!is_string($password) || $password === '' || $username === '') {
            throw new RuntimeException('No active connection is available to save.', 409);
        }

        return array(
            'driver' => defined('Adminer\\DRIVER') ? (string) constant('Adminer\\DRIVER') : '',
            'server' => defined('Adminer\\SERVER') ? (string) constant('Adminer\\SERVER') : '',
            'username' => $username,
            'password' => $password,
            'db' => defined('Adminer\\DB') ? (string) constant('Adminer\\DB') : '',
        );
    }

    private function getCurrentUsername(): string
    {
        $username = trim((string) ($_GET['username'] ?? ''));
        if ($username !== '') {
            return $username;
        }
        if (!defined('Adminer\\DRIVER') || !defined('Adminer\\SERVER')) {
            return '';
        }

        $driver = (string) constant('Adminer\\DRIVER');
        $server = (string) constant('Adminer\\SERVER');
        $usernames = $_SESSION['pwds'][$driver][$server] ?? null;
        if (!is_array($usernames)) {
            return '';
        }

        $candidates = array_keys($usernames);
        return count($candidates) === 1 ? (string) $candidates[0] : '';
    }

    private function getStoredPassword(string $username): ?string
    {
        if (!defined('Adminer\\DRIVER') || !defined('Adminer\\SERVER')) {
            return null;
        }

        $driver = (string) constant('Adminer\\DRIVER');
        $server = (string) constant('Adminer\\SERVER');
        $storedPassword = $_SESSION['pwds'][$driver][$server][$username] ?? null;

        if (is_string($storedPassword)) {
            return $storedPassword;
        }

        if (
            is_array($storedPassword) &&
            isset($storedPassword[0]) &&
            is_string($storedPassword[0]) &&
            !empty($_COOKIE['adminer_key'])
        ) {
            $decrypted = Adminer\decrypt_string($storedPassword[0], (string) $_COOKIE['adminer_key']);
            return is_string($decrypted) ? $decrypted : null;
        }

        return null;
    }
}
