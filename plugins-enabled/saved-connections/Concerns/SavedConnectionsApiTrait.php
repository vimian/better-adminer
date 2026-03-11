<?php

trait SavedConnectionsApiTrait
{
    public function headers()
    {
        if (!isset($_GET[self::API_PARAM])) {
            return;
        }

        Adminer\restart_session();

        try {
            $this->verifyApiToken();
            $this->dispatchApiAction((string) ($_GET['action'] ?? ''));
        } catch (\Throwable $exception) {
            $code = (int) $exception->getCode();
            if ($code < 400 || $code > 599) {
                $code = 400;
            }

            $this->sendJson(array('error' => $exception->getMessage()), $code);
        }
    }

    private function dispatchApiAction(string $action): void
    {
        switch ($action) {
            case 'list':
                $this->sendJson(array('connections' => $this->listConnections()));
                return;

            case 'save':
                $this->requireMethod('POST');
                $this->sendJson(array('connection' => $this->saveConnection($this->readJsonBody())));
                return;

            case 'save_bookmark':
                $this->requireMethod('POST');
                $this->sendJson(array('connection' => $this->saveBookmark($this->readJsonBody())));
                return;

            case 'delete':
                $this->requireMethod('POST');
                $this->deleteConnection($this->readJsonBody());
                $this->sendJson(array('connections' => $this->listConnections()));
                return;

            case 'delete_bookmark':
                $this->requireMethod('POST');
                $this->sendJson(array('connection' => $this->deleteBookmark($this->readJsonBody())));
                return;

            case 'link_connection':
                $this->requireMethod('POST');
                $this->sendJson(array('connection' => $this->linkConnection($this->readJsonBody())));
                return;

            case 'current':
                $this->sendJson(array('connection' => $this->getCurrentConnection()));
                return;
        }

        $this->sendJson(array('error' => 'Unknown action.'), 404);
    }

    private function verifyApiToken(): void
    {
        $provided = (string) ($_SERVER['HTTP_X_ADMINER_TOKEN'] ?? '');
        $expected = (string) ($_SESSION['token'] ?? '');
        if ($provided === '' || $expected === '' || !hash_equals($expected, $provided)) {
            throw new RuntimeException('Invalid session token.', 403);
        }
    }

    private function requireMethod(string $method): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== $method) {
            throw new RuntimeException('Method not allowed.', 405);
        }
    }

    private function readJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw ?: '{}', true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid JSON body.', 400);
        }

        return $decoded;
    }

    private function sendJson(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
        exit;
    }
}
