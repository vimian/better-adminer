<?php

class SavedConnectionsPlugin extends Adminer\Plugin
{
    private const API_PARAM = 'saved_connections_api';

    private string $storageFile;

    public function __construct(string $storageDir = '/var/lib/adminer')
    {
        $this->storageFile = rtrim($storageDir, '/').'/saved-connections.json';
    }

    public function headers()
    {
        if (!isset($_GET[self::API_PARAM])) {
            return;
        }

        Adminer\restart_session();

        try {
            $this->verifyApiToken();

            $action = (string) ($_GET['action'] ?? '');
            if ($action === 'list') {
                $this->sendJson(array('connections' => $this->listConnections()));
            }

            if ($action === 'save') {
                $this->requireMethod('POST');
                $payload = $this->readJsonBody();
                $record = $this->saveConnection($payload);
                $this->sendJson(array('connection' => $record));
            }

            if ($action === 'save_bookmark') {
                $this->requireMethod('POST');
                $payload = $this->readJsonBody();
                $record = $this->saveBookmark($payload);
                $this->sendJson(array('connection' => $record));
            }

            if ($action === 'delete') {
                $this->requireMethod('POST');
                $payload = $this->readJsonBody();
                $this->deleteConnection($payload);
                $this->sendJson(array('connections' => $this->listConnections()));
            }

            if ($action === 'delete_bookmark') {
                $this->requireMethod('POST');
                $payload = $this->readJsonBody();
                $record = $this->deleteBookmark($payload);
                $this->sendJson(array('connection' => $record));
            }

            if ($action === 'link_connection') {
                $this->requireMethod('POST');
                $payload = $this->readJsonBody();
                $record = $this->linkConnection($payload);
                $this->sendJson(array('connection' => $record));
            }

            if ($action === 'current') {
                $this->sendJson(array('connection' => $this->getCurrentConnection()));
            }

            $this->sendJson(array('error' => 'Unknown action.'), 404);
        } catch (\Throwable $exception) {
            $code = (int) $exception->getCode();
            if ($code < 400 || $code > 599) {
                $code = 400;
            }

            $this->sendJson(array('error' => $exception->getMessage()), $code);
        }
    }

    public function head($darkMode = null)
    {
        echo <<<'HTML'
<style>
.saved-connections-card {
    margin: 0;
    font: 90% / 1.25 Verdana, Arial, Helvetica, sans-serif;
}
.saved-connections-card[hidden] {
    display: none;
}
.saved-connections-card__empty,
.saved-connections-card__meta,
.saved-connections-modal__hint,
.saved-connections-bookmarks__subtitle {
    color: #777;
    font-size: 12px;
    line-height: 1.4;
}
.saved-connections-card__list,
.saved-connections-bookmarks__list {
    margin: 0;
}
.saved-connections-card__item,
.saved-connections-bookmarks__item,
.saved-connections-card__empty {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
    padding: .8em 1em;
    border-bottom: 1px solid #ccc;
    background: var(--bg);
}
.saved-connections-card__empty {
    display: block;
}
.saved-connections-card__open,
.saved-connections-bookmarks__open {
    display: block;
    width: 100%;
    text-align: left;
    padding: 0;
    border: 0;
    background: none;
    color: var(--fg);
    cursor: pointer;
    font: inherit;
}
.saved-connections-card__open:hover,
.saved-connections-card__forget:hover,
.saved-connections-bookmarks__open:hover,
.saved-connections-bookmarks__forget:hover {
    opacity: .7;
}
.saved-connections-card__actions {
    display: flex;
    align-items: center;
}
.saved-connections-card__forget,
.saved-connections-bookmarks__forget {
    padding: 0;
    border: 0;
    background: none;
    color: var(--fg);
    cursor: pointer;
    font: inherit;
}
.saved-connections-card__title {
    display: block;
}
.saved-connections-launcher {
    font: 90% / 1.25 Verdana, Arial, Helvetica, sans-serif;
}
.saved-connections-floating {
    position: fixed;
    right: 24px;
    bottom: 24px;
    z-index: 1000;
    box-shadow: 0 8px 24px rgba(20, 40, 60, 0.15);
}
.saved-connections-bookmarks {
    position: fixed;
    right: 24px;
    bottom: 78px;
    z-index: 990;
    width: min(26em, calc(100vw - 48px));
    max-height: min(55vh, 32em);
    overflow-y: auto;
    overflow-x: hidden;
    border: 1px solid #ccc;
    background: var(--bg);
    box-shadow: 0 8px 24px rgba(20, 40, 60, 0.15);
}
.saved-connections-bookmarks[hidden] {
    display: none;
}
.saved-connections-bookmarks__header {
    padding: .7em 1em;
    border-bottom: 1px solid #ccc;
    background: var(--dim);
}
.saved-connections-bookmarks__title {
    margin: 0;
    font-size: 13px;
    color: var(--fg);
}
.saved-connections-bookmarks__subtitle {
    display: none;
}
.saved-connections-bookmarks__item {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    grid-template-areas:
        "content content"
        "meta actions";
    align-items: end;
}
.saved-connections-bookmarks__open {
    grid-area: content;
    min-width: 0;
}
.saved-connections-bookmarks__label {
    display: block;
    white-space: normal;
    overflow-wrap: anywhere;
    word-break: break-word;
}
.saved-connections-bookmarks__meta {
    grid-area: meta;
    padding-top: .5em;
    overflow-wrap: anywhere;
    word-break: break-word;
}
.saved-connections-bookmarks__actions {
    grid-area: actions;
    justify-self: end;
    align-self: end;
    padding-top: .5em;
}
.saved-connections-modal {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2000;
    padding: 16px;
}
.saved-connections-modal[hidden] {
    display: none;
}
.saved-connections-modal__dialog {
    width: min(28em, 100%);
    border: 1px solid #999;
    background: var(--bg);
    color: var(--fg);
    padding: 0 1em 1em;
    box-shadow: 0 0 20px -3px var(--fg);
    font: 90% / 1.25 Verdana, Arial, Helvetica, sans-serif;
}
.saved-connections-modal__title {
    margin: 0 -0.667em .8em;
    padding: .8em .667em;
    border-bottom: 1px solid #999;
    font-size: 150%;
    font-weight: normal;
    color: #777;
    background: var(--dim);
}
.saved-connections-modal__description {
    margin: 0 0 .8em;
    color: var(--fg);
    font-size: 100%;
    line-height: 1.25;
}
.saved-connections-modal__field {
    margin-bottom: .8em;
}
.saved-connections-modal__field label {
    display: block;
    margin-bottom: .2em;
    font-weight: normal;
}
.saved-connections-modal__field input {
    width: 100%;
    box-sizing: border-box;
    font: inherit;
}
.saved-connections-modal__pin {
    display: flex;
    gap: .5em;
}
.saved-connections-modal__pin-slot {
    width: 3em;
    height: auto;
    border: 1px solid #999;
    border-radius: 0;
    background: var(--bg);
    color: var(--fg);
    text-align: center;
    line-height: 1.25;
    padding: .2em .3em;
}
.saved-connections-modal__pin-slot:focus {
    outline: 2px solid var(--fg);
    outline-offset: 2px;
}
.saved-connections-modal__actions {
    margin-top: .8em;
    text-align: right;
}
.saved-connections-modal__submit {
    border: 1px solid #999;
    background: var(--dim);
    color: var(--fg);
    cursor: pointer;
    padding: .2em .6em;
    font: inherit;
}
.saved-connections-modal__submit:hover {
    background: var(--lit);
}
.saved-connections-modal__error {
    color: red;
    background: #fee;
    font-size: 100%;
    line-height: 1.25;
    padding: .5em .8em;
    margin: .8em 0 0;
}
.saved-connections-modal__error:empty {
    display: none;
}
.saved-connections-toast {
    position: fixed;
    left: 50%;
    bottom: 18px;
    transform: translateX(-50%);
    background: #17324d;
    color: #fff;
    padding: 10px 14px;
    border-radius: 999px;
    z-index: 2100;
    box-shadow: 0 10px 28px rgba(10, 20, 30, 0.25);
}
@media (max-width: 640px) {
    .saved-connections-card__item,
    .saved-connections-bookmarks__item {
        flex-direction: column;
        align-items: stretch;
    }
    .saved-connections-card__actions {
        justify-content: flex-end;
    }
    .saved-connections-floating {
        left: 16px;
        right: 16px;
        bottom: 16px;
    }
    .saved-connections-bookmarks {
        left: 16px;
        right: 16px;
        width: auto;
        bottom: 66px;
    }
    .saved-connections-modal__pin-slot {
        width: calc((100% - 1.5em) / 4);
    }
}
</style>
HTML;

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

        echo Adminer\script(<<<'JS'
(() => {
    const config = window.AdminerSavedConnectionsConfig;
    if (!config || !window.crypto?.subtle) {
        return;
    }

    const encoder = new TextEncoder();
    const decoder = new TextDecoder();
    let cachedConnections = [];
    let currentConnection = null;
    let currentFingerprint = "";
    let currentConnectionError = "";
    let toastTimer = null;
    let modalState = null;

    document.addEventListener("DOMContentLoaded", () => {
        injectSavedConnectionsPanel();
        injectBookmarksPanel();
        injectCurrentConnectionButton();
        void initializePageState();
    });

    async function initializePageState() {
        if (!getAuthForm()) {
            try {
                await ensureCurrentConnection();
            } catch (error) {
                // renderBookmarks handles the failure state.
            }
        }
        await refreshConnections();
    }

    function endpointUrl(action, useCurrentContext = false) {
        const url = new URL(useCurrentContext ? config.currentEndpoint : config.endpoint, window.location.origin);
        url.searchParams.set(config.apiParam, "1");
        url.searchParams.set("action", action);
        return url;
    }

    async function apiRequest(action, options = {}) {
        const headers = new Headers(options.headers || {});
        headers.set("X-Adminer-Token", config.token);

        if (options.body && !headers.has("Content-Type")) {
            headers.set("Content-Type", "application/json");
        }

        const response = await fetch(endpointUrl(action, options.useCurrentContext === true), {
            method: options.method || "GET",
            credentials: "same-origin",
            ...options,
            headers,
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(data.error || "Request failed.");
        }
        return data;
    }

    async function refreshConnections() {
        try {
            const data = await apiRequest("list");
            cachedConnections = Array.isArray(data.connections) ? data.connections : [];
            renderSavedConnections();
            renderBookmarks();
        } catch (error) {
            renderSavedConnections(error.message);
            renderBookmarks(error.message);
        }
    }

    function injectSavedConnectionsPanel() {
        if (!getAuthForm() || document.querySelector("[data-saved-connections-panel]")) {
            return;
        }

        const menu = document.querySelector("#menu");
        const heading = menu?.querySelector("h1");
        if (!menu || !heading) {
            return;
        }

        const panel = document.createElement("div");
        panel.className = "saved-connections-card";
        panel.hidden = true;
        panel.dataset.savedConnectionsPanel = "1";
        panel.innerHTML = '<div class="saved-connections-card__list" data-saved-connections-list></div>';
        heading.insertAdjacentElement("afterend", panel);
    }

    function renderSavedConnections(errorMessage = "") {
        const panel = document.querySelector("[data-saved-connections-panel]");
        const list = document.querySelector("[data-saved-connections-list]");
        if (!panel || !list) {
            return;
        }

        list.textContent = "";

        if (errorMessage) {
            panel.hidden = false;
            const error = document.createElement("div");
            error.className = "saved-connections-card__empty";
            error.textContent = errorMessage;
            list.appendChild(error);
            return;
        }

        if (!cachedConnections.length) {
            panel.hidden = true;
            return;
        }

        panel.hidden = false;

        for (const connection of cachedConnections) {
            const item = document.createElement("div");
            item.className = "saved-connections-card__item";

            const openButton = document.createElement("button");
            openButton.type = "button";
            openButton.className = "saved-connections-card__open";
            openButton.innerHTML =
                `<strong class="saved-connections-card__title">${escapeHtml(connection.label || "Saved connection")}</strong>` +
                `<div class="saved-connections-card__meta">Updated ${formatDate(connection.updatedAt)} • ${formatCount(connection.bookmarks?.length || 0, "bookmark")}</div>`;
            openButton.addEventListener("click", () => unlockAndLogin(connection));

            const actions = document.createElement("div");
            actions.className = "saved-connections-card__actions";

            const forgetButton = document.createElement("button");
            forgetButton.type = "button";
            forgetButton.className = "saved-connections-card__forget";
            forgetButton.textContent = "Forget";
            forgetButton.addEventListener("click", async () => {
                if (!window.confirm(`Forget "${connection.label}"?`)) {
                    return;
                }
                try {
                    await apiRequest("delete", {
                        method: "POST",
                        body: JSON.stringify({ id: connection.id }),
                    });
                    showToast("Connection removed.");
                    await refreshConnections();
                } catch (error) {
                    showToast(error.message);
                }
            });

            actions.appendChild(forgetButton);
            item.appendChild(openButton);
            item.appendChild(actions);
            list.appendChild(item);
        }
    }

    function injectBookmarksPanel() {
        if (getAuthForm() || document.querySelector("[data-saved-bookmarks-panel]")) {
            return;
        }

        const panel = document.createElement("section");
        panel.className = "saved-connections-card saved-connections-bookmarks";
        panel.hidden = true;
        panel.dataset.savedBookmarksPanel = "1";
        panel.innerHTML = `
            <div class="saved-connections-bookmarks__header">
                <div class="saved-connections-bookmarks__title">Bookmarks</div>
                <div class="saved-connections-bookmarks__subtitle" data-saved-bookmarks-subtitle></div>
            </div>
            <div class="saved-connections-bookmarks__list" data-saved-bookmarks-list></div>
        `;
        document.body.appendChild(panel);
    }

    function renderBookmarks(errorMessage = "") {
        const panel = document.querySelector("[data-saved-bookmarks-panel]");
        const subtitle = document.querySelector("[data-saved-bookmarks-subtitle]");
        const list = document.querySelector("[data-saved-bookmarks-list]");
        if (!panel || !subtitle || !list) {
            return;
        }

        list.textContent = "";

        if (errorMessage) {
            panel.hidden = false;
            subtitle.textContent = "";
            const error = document.createElement("div");
            error.className = "saved-connections-card__empty";
            error.textContent = errorMessage;
            list.appendChild(error);
            return;
        }

        if (currentConnectionError) {
            panel.hidden = false;
            subtitle.textContent = "";
            const error = document.createElement("div");
            error.className = "saved-connections-card__empty";
            error.textContent = currentConnectionError;
            list.appendChild(error);
            return;
        }

        if (!currentFingerprint) {
            panel.hidden = true;
            return;
        }

        panel.hidden = false;
        const connection = findConnectionByFingerprint(currentFingerprint);
        if (!connection) {
            subtitle.textContent = "";
            const empty = document.createElement("div");
            empty.className = "saved-connections-card__empty";
            empty.textContent = "No saved credentials are linked to this session yet.";
            list.appendChild(empty);
            return;
        }

        subtitle.textContent = "";
        if (!Array.isArray(connection.bookmarks) || !connection.bookmarks.length) {
            const empty = document.createElement("div");
            empty.className = "saved-connections-card__empty";
            empty.textContent = "No bookmarks saved for this connection yet.";
            list.appendChild(empty);
            return;
        }

        for (const bookmark of connection.bookmarks) {
            const item = document.createElement("div");
            item.className = "saved-connections-bookmarks__item";

            const openButton = document.createElement("button");
            openButton.type = "button";
            openButton.className = "saved-connections-bookmarks__open";
            openButton.innerHTML =
                `<strong>${escapeHtml(bookmark.label || "Bookmark")}</strong>` +
                `<div class="saved-connections-card__meta">${escapeHtml(bookmark.url || "/")} • Updated ${formatDate(bookmark.updatedAt)}</div>`;
            openButton.addEventListener("click", () => openBookmarkInBackground(bookmark.url));

            const label = openButton.querySelector("strong");
            if (label) {
                label.className = "saved-connections-bookmarks__label";
                label.textContent = getBookmarkDisplayLabel(bookmark, connection);
            }

            const inlineMeta = openButton.querySelector(".saved-connections-card__meta");
            if (inlineMeta) {
                inlineMeta.remove();
            }

            const meta = document.createElement("div");
            meta.className = "saved-connections-card__meta saved-connections-bookmarks__meta";
            meta.textContent = `Updated ${formatDate(bookmark.updatedAt)}`;

            const actions = document.createElement("div");
            actions.className = "saved-connections-card__actions saved-connections-bookmarks__actions";

            const forgetButton = document.createElement("button");
            forgetButton.type = "button";
            forgetButton.className = "saved-connections-bookmarks__forget";
            forgetButton.textContent = "Forget";
            forgetButton.addEventListener("click", async () => {
                if (!window.confirm(`Forget bookmark "${bookmark.label}"?`)) {
                    return;
                }
                try {
                    await apiRequest("delete_bookmark", {
                        method: "POST",
                        body: JSON.stringify({
                            connectionId: connection.id,
                            bookmarkId: bookmark.id,
                        }),
                    });
                    showToast("Bookmark removed.");
                    await refreshConnections();
                } catch (error) {
                    showToast(error.message);
                }
            });

            actions.appendChild(forgetButton);
            item.appendChild(openButton);
            item.appendChild(meta);
            item.appendChild(actions);
            list.appendChild(item);
        }
    }

    function injectCurrentConnectionButton() {
        const authForm = getAuthForm();
        if (authForm) {
            const saveButton = document.querySelector("[data-saved-connections-save]");
            const beginSaveAndLogin = event => {
                event.preventDefault();
                const connection = readAuthForm(authForm);
                if (!connection.server || !connection.username || !connection.password) {
                    showToast("Fill in server, username, and password first.");
                    return;
                }
                void saveConnectionAndLogin(connection, () => authForm.submit());
            };
            if (saveButton) {
                saveButton.addEventListener("click", beginSaveAndLogin);
            }
            authForm.addEventListener("submit", beginSaveAndLogin);
            return;
        }

        const launcher = document.createElement("button");
        launcher.type = "button";
        launcher.className = "saved-connections-launcher saved-connections-floating";
        launcher.textContent = "Add bookmark";
        launcher.addEventListener("click", async () => {
            try {
                await saveCurrentPage();
            } catch (error) {
                showToast(error.message);
            }
        });
        document.body.appendChild(launcher);
    }

    function getAuthForm() {
        return document.querySelector("input[name='auth[server]']")?.form || null;
    }

    function readAuthForm(form) {
        return {
            driver: form.elements["auth[driver]"]?.value || "",
            server: form.elements["auth[server]"]?.value.trim() || "",
            username: form.elements["auth[username]"]?.value.trim() || "",
            password: form.elements["auth[password]"]?.value || "",
            db: form.elements["auth[db]"]?.value.trim() || "",
        };
    }

    function writeAuthForm(form, connection) {
        form.elements["auth[driver]"].value = connection.driver || "server";
        form.elements["auth[server]"].value = connection.server || "";
        form.elements["auth[username]"].value = connection.username || "";
        form.elements["auth[password]"].value = connection.password || "";
        form.elements["auth[db]"].value = connection.db || "";
    }

    async function saveConnectionAndLogin(connection, onSuccess = null, initialBookmark = null) {
        const fingerprint = await fingerprintConnection(connection);
        const existingConnection = findConnectionByFingerprint(fingerprint);
        if (existingConnection && !initialBookmark) {
            if (typeof onSuccess === "function") {
                onSuccess();
            }
            return;
        }

        const suggestedLabel = [connection.server, connection.db, connection.username]
            .filter(Boolean)
            .join(" / ");
        openModal({
            title: initialBookmark ? "Save encrypted connection and bookmark" : "Save encrypted connection",
            description: initialBookmark
                ? "The container stores only encrypted connection data. Choose any 4-character PIN to save these credentials and this page bookmark together."
                : "The container stores only encrypted connection data. Choose any 4-character PIN. The PIN is never stored.",
            showLabelField: !existingConnection,
            defaultLabel: existingConnection?.label || suggestedLabel,
            submit: async ({ label, pin }) => {
                const payload = await encryptConnection(connection, pin);
                const saveLabel = (label || existingConnection?.label || suggestedLabel).trim();
                await apiRequest("save", {
                    method: "POST",
                    body: JSON.stringify({
                        label: saveLabel,
                        payload,
                        fingerprint,
                        bookmark: initialBookmark,
                    }),
                });
                await refreshConnections();
                showToast(initialBookmark ? `Saved "${saveLabel}" and bookmarked this page.` : `Saved "${saveLabel}".`);
                if (typeof onSuccess === "function") {
                    onSuccess();
                }
            },
        });
    }

    async function unlockAndLogin(record) {
        const authForm = getAuthForm();
        if (!authForm) {
            showToast("Open a login page to use a saved connection.");
            return;
        }

        openModal({
            title: `Unlock ${record.label}`,
            description: "Enter the 4-character PIN for this connection.",
            showLabelField: false,
            submit: async ({ pin }) => {
                const decrypted = await decryptConnection(record.payload, pin);
                const fingerprint = await fingerprintConnection(decrypted);
                if (record.id && fingerprint && record.fingerprint !== fingerprint) {
                    await apiRequest("link_connection", {
                        method: "POST",
                        body: JSON.stringify({ id: record.id, fingerprint }),
                    });
                }
                writeAuthForm(authForm, decrypted);
                authForm.submit();
            },
        });
    }

    async function ensureCurrentConnection() {
        if (currentConnection || getAuthForm()) {
            return currentConnection;
        }

        try {
            const data = await apiRequest("current", { useCurrentContext: true });
            currentConnection = data.connection || null;
            currentFingerprint = currentConnection ? await fingerprintConnection(currentConnection) : "";
            currentConnectionError = "";
            return currentConnection;
        } catch (error) {
            currentConnectionError = error.message || "Unable to inspect the current connection.";
            throw error;
        }
    }

    async function saveCurrentPage() {
        const connection = await ensureCurrentConnection();
        if (!connection) {
            throw new Error("No active connection is available to save.");
        }

        const bookmark = getCurrentBookmark(connection);
        const fingerprint = currentFingerprint || await fingerprintConnection(connection);
        const existingConnection = findConnectionByFingerprint(fingerprint);

        if (!existingConnection) {
            await saveConnectionAndLogin(connection, null, bookmark);
            return;
        }

        await apiRequest("save_bookmark", {
            method: "POST",
            body: JSON.stringify({ fingerprint, bookmark }),
        });
        await refreshConnections();
        showToast(`Bookmarked "${bookmark.label}".`);
    }

    function getCurrentBookmark(connection) {
        const relativeUrl = `${window.location.pathname}${window.location.search}${window.location.hash}`;
        const headingText =
            document.querySelector("#breadcrumb")?.textContent?.replace(/\s+/g, " ").trim() ||
            document.querySelector("h2")?.textContent?.trim() ||
            "";
        const titleText = cleanBookmarkLabel(document.title);
        const label = getBookmarkUrlSuffix(relativeUrl, connection) || headingText || titleText || relativeUrl;

        return {
            label: label.slice(0, 120),
            url: relativeUrl,
        };
    }

    function getBookmarkDisplayLabel(bookmark, connection) {
        return getBookmarkUrlSuffix(bookmark.url || "", connection) ||
            cleanBookmarkLabel(bookmark.label || "") ||
            cleanBookmarkLabel(bookmark.url || "") ||
            "Bookmark";
    }

    function getBookmarkUrlSuffix(url, connection) {
        if (!url) {
            return "";
        }

        const decodedUrl = decodeURIComponent(String(url).replace(/\+/g, "%20"));
        const namespaceMatch = decodedUrl.match(/ns=public(?:&(.*))?$/i);
        if (namespaceMatch) {
            return (namespaceMatch[1] || "").trim();
        }

        const parsed = new URL(url, window.location.origin);
        const params = new URLSearchParams(parsed.search);
        const driverKey = connection?.driver || "";
        if (driverKey && connection?.server && params.get(driverKey) === connection.server) {
            params.delete(driverKey);
        }
        if (params.get("username") === (connection?.username || "")) {
            params.delete("username");
        }
        if (params.get("db") === (connection?.db || "")) {
            params.delete("db");
        }
        params.delete("ns");

        const suffix = [];
        const search = decodeURIComponent(params.toString().replace(/\+/g, "%20"));
        if (parsed.pathname && parsed.pathname !== "/") {
            suffix.push(parsed.pathname.replace(/^\/+/, ""));
        }
        if (search) {
            suffix.push(search);
        }
        if (parsed.hash) {
            suffix.push(parsed.hash.slice(1));
        }

        return suffix.join(" ").trim();
    }

    function cleanBookmarkLabel(value) {
        return String(value || "")
            .replace(/\bAdminer\s*\d[\d.]*\b/gi, "")
            .replace(/\s*-\s*Adminer\s*$/i, "")
            .replace(/\s+Adminer\s*$/i, "")
            .replace(/\s+/g, " ")
            .trim();
    }

    function findConnectionByFingerprint(fingerprint) {
        if (!fingerprint) {
            return null;
        }
        return cachedConnections.find(connection => connection.fingerprint === fingerprint) || null;
    }

    function openBookmarkInBackground(url) {
        if (!url) {
            return;
        }

        const anchor = document.createElement("a");
        anchor.href = new URL(url, window.location.origin).toString();
        anchor.target = "_blank";
        anchor.rel = "noopener noreferrer";
        anchor.style.display = "none";
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
        window.focus();
    }

    function openModal(options) {
        closeModal();

        const overlay = document.createElement("div");
        overlay.className = "saved-connections-modal";

        const dialog = document.createElement("div");
        dialog.className = "saved-connections-modal__dialog";

        const title = document.createElement("h3");
        title.className = "saved-connections-modal__title";
        title.textContent = options.title;

        const description = document.createElement("p");
        description.className = "saved-connections-modal__description";
        description.textContent = options.description;

        const form = document.createElement("form");
        form.addEventListener("submit", event => {
            event.preventDefault();
            void trySubmit();
        });

        let labelInput = null;
        if (options.showLabelField) {
            const labelField = document.createElement("div");
            labelField.className = "saved-connections-modal__field";

            const label = document.createElement("label");
            label.textContent = "Connection name";

            labelInput = document.createElement("input");
            labelInput.type = "text";
            labelInput.required = true;
            labelInput.maxLength = 120;
            labelInput.value = options.defaultLabel || "";

            labelField.append(label, labelInput);
            form.appendChild(labelField);
        }

        const pinField = document.createElement("div");
        pinField.className = "saved-connections-modal__field";

        const pinLabel = document.createElement("label");
        pinLabel.textContent = "PIN";

        const pinWrap = document.createElement("div");
        pinWrap.className = "saved-connections-modal__pin";

        const pinInputs = Array.from({ length: 4 }, (_, index) => {
            const input = document.createElement("input");
            input.type = "password";
            input.required = true;
            input.maxLength = 1;
            input.autocomplete = "off";
            input.inputMode = "text";
            input.className = "saved-connections-modal__pin-slot";
            input.setAttribute("aria-label", `PIN character ${index + 1}`);
            pinWrap.appendChild(input);
            return input;
        });

        const hint = document.createElement("div");
        hint.className = "saved-connections-modal__hint";
        hint.textContent = "Exactly 4 characters.";

        pinField.append(pinLabel, pinWrap, hint);
        form.appendChild(pinField);

        const error = document.createElement("div");
        error.className = "saved-connections-modal__error";
        form.appendChild(error);

        const actions = document.createElement("div");
        actions.className = "saved-connections-modal__actions";

        const submitButton = document.createElement("button");
        submitButton.type = "submit";
        submitButton.className = "saved-connections-modal__submit";
        submitButton.textContent = options.showLabelField ? "Save" : "Unlock";
        actions.appendChild(submitButton);
        form.appendChild(actions);

        dialog.append(title, description, form);
        overlay.appendChild(dialog);
        document.body.appendChild(overlay);

        modalState = { overlay };

        const trySubmit = async () => {
            const label = labelInput ? labelInput.value.trim() : "";
            const pin = pinInputs.map(input => input.value).join("");

            if (labelInput && !label) {
                return;
            }

            if (pin.length !== 4 || pinInputs.some(input => input.value.length !== 1)) {
                if (pin.length >= 4) {
                    error.textContent = "PIN must be exactly 4 characters.";
                }
                return;
            }

            if (form.dataset.busy === "1") {
                return;
            }

            form.dataset.busy = "1";
            error.textContent = "";

            try {
                await options.submit({ label, pin });
                closeModal();
            } catch (submitError) {
                error.textContent = submitError.message || "Unable to continue.";
                pinInputs.forEach(input => {
                    input.value = "";
                });
                pinInputs[0].focus();
            } finally {
                delete form.dataset.busy;
            }
        };

        overlay.addEventListener("click", event => {
            if (event.target === overlay) {
                closeModal();
            }
        });

        document.addEventListener("keydown", onModalKeydown, { once: true });
        pinInputs.forEach((input, index) => {
            input.addEventListener("input", event => {
                const target = event.currentTarget;
                const value = target.value;
                target.value = value ? value.slice(-1) : "";
                error.textContent = "";

                if (target.value && index < pinInputs.length - 1) {
                    pinInputs[index + 1].focus();
                    pinInputs[index + 1].select();
                }

                if (pinInputs.every(item => item.value.length === 1)) {
                    void trySubmit();
                }
            });

            input.addEventListener("keydown", event => {
                if (event.key === "Escape") {
                    closeModal();
                    return;
                }

                if (event.key === "Enter") {
                    event.preventDefault();
                    void trySubmit();
                    return;
                }

                if (event.key === "Backspace" && !input.value && index > 0) {
                    pinInputs[index - 1].focus();
                    pinInputs[index - 1].value = "";
                    event.preventDefault();
                }

                if (event.key === "ArrowLeft" && index > 0) {
                    pinInputs[index - 1].focus();
                    event.preventDefault();
                }

                if (event.key === "ArrowRight" && index < pinInputs.length - 1) {
                    pinInputs[index + 1].focus();
                    event.preventDefault();
                }
            });

            input.addEventListener("paste", event => {
                event.preventDefault();
                const pasted = (event.clipboardData?.getData("text") || "").slice(0, 4);
                for (let pasteIndex = 0; pasteIndex < pinInputs.length; pasteIndex += 1) {
                    pinInputs[pasteIndex].value = pasted[pasteIndex] || "";
                }
                const nextIndex = Math.min(pasted.length, pinInputs.length - 1);
                pinInputs[nextIndex].focus();
                if (pinInputs.every(item => item.value.length === 1)) {
                    void trySubmit();
                }
            });
        });
        if (labelInput) {
            labelInput.addEventListener("keydown", event => {
                if (event.key === "Escape") {
                    closeModal();
                } else if (event.key === "Enter") {
                    event.preventDefault();
                    pinInputs[0].focus();
                }
            });
            (labelInput.value ? pinInputs[0] : labelInput).focus();
        } else {
            pinInputs[0].focus();
        }
    }

    function onModalKeydown(event) {
        if (event.key === "Escape") {
            closeModal();
            return;
        }
        document.addEventListener("keydown", onModalKeydown, { once: true });
    }

    function closeModal() {
        if (!modalState) {
            return;
        }
        modalState.overlay.remove();
        modalState = null;
    }

    async function encryptConnection(connection, pin) {
        const salt = crypto.getRandomValues(new Uint8Array(16));
        const iv = crypto.getRandomValues(new Uint8Array(12));
        const key = await deriveKey(pin, salt, ["encrypt"]);
        const ciphertext = await crypto.subtle.encrypt(
            { name: "AES-GCM", iv },
            key,
            encoder.encode(JSON.stringify(connection))
        );

        return {
            version: 1,
            algorithm: "AES-GCM",
            kdf: "PBKDF2-SHA-256",
            iterations: 250000,
            salt: bytesToBase64(salt),
            iv: bytesToBase64(iv),
            ciphertext: bytesToBase64(new Uint8Array(ciphertext)),
        };
    }

    async function decryptConnection(payload, pin) {
        if (!payload || !payload.salt || !payload.iv || !payload.ciphertext) {
            throw new Error("Stored connection is incomplete.");
        }

        const salt = base64ToBytes(payload.salt);
        const iv = base64ToBytes(payload.iv);
        const ciphertext = base64ToBytes(payload.ciphertext);
        const key = await deriveKey(pin, salt, ["decrypt"], payload.iterations || 250000);
        const plaintext = await crypto.subtle.decrypt(
            { name: "AES-GCM", iv },
            key,
            ciphertext
        );
        return JSON.parse(decoder.decode(plaintext));
    }

    async function deriveKey(pin, salt, usages, iterations = 250000) {
        const keyMaterial = await crypto.subtle.importKey(
            "raw",
            encoder.encode(pin),
            "PBKDF2",
            false,
            ["deriveKey"]
        );
        return crypto.subtle.deriveKey(
            {
                name: "PBKDF2",
                salt,
                iterations,
                hash: "SHA-256",
            },
            keyMaterial,
            { name: "AES-GCM", length: 256 },
            false,
            usages
        );
    }

    async function fingerprintConnection(connection) {
        const normalized = JSON.stringify({
            driver: connection?.driver || "",
            server: connection?.server || "",
            username: connection?.username || "",
            db: connection?.db || "",
        });
        const digest = await crypto.subtle.digest("SHA-256", encoder.encode(normalized));
        return Array.from(new Uint8Array(digest), byte => byte.toString(16).padStart(2, "0")).join("");
    }

    function bytesToBase64(bytes) {
        let binary = "";
        bytes.forEach(byte => {
            binary += String.fromCharCode(byte);
        });
        return window.btoa(binary);
    }

    function base64ToBytes(value) {
        const binary = window.atob(value);
        const bytes = new Uint8Array(binary.length);
        for (let index = 0; index < binary.length; index += 1) {
            bytes[index] = binary.charCodeAt(index);
        }
        return bytes;
    }

    function formatDate(value) {
        if (!value) {
            return "recently";
        }
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return "recently";
        }
        return date.toLocaleString();
    }

    function formatCount(value, singular) {
        const count = Number(value) || 0;
        return `${count} ${singular}${count === 1 ? "" : "s"}`;
    }

    function escapeHtml(value) {
        return String(value)
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#39;");
    }

    function showToast(message) {
        let toast = document.querySelector("[data-saved-connections-toast]");
        if (!toast) {
            toast = document.createElement("div");
            toast.className = "saved-connections-toast";
            toast.dataset.savedConnectionsToast = "1";
            document.body.appendChild(toast);
        }
        toast.textContent = message;
        toast.hidden = false;
        if (toastTimer) {
            window.clearTimeout(toastTimer);
        }
        toastTimer = window.setTimeout(() => {
            toast.hidden = true;
        }, 2500);
    }
})();
JS
        );

        return true;
    }

    public function loginFormField($name, $heading, $input)
    {
        return $heading.$input."\n";
    }

    public function loginForm()
    {
        $driver = defined('Adminer\\DRIVER') ? (string) constant('Adminer\\DRIVER') : 'server';
        $server = defined('Adminer\\SERVER') ? (string) constant('Adminer\\SERVER') : '';
        $db = defined('Adminer\\DB') ? (string) constant('Adminer\\DB') : '';
        $username = (string) ($_GET['username'] ?? '');

        echo "<table class='layout'>\n";
        echo Adminer\adminer()->loginFormField(
            'driver',
            '<tr><th>'.Adminer\lang(33).'<td>',
            Adminer\html_select('auth[driver]', Adminer\SqlDriver::$drivers, $driver, 'loginDriver(this);')
        );
        echo Adminer\adminer()->loginFormField(
            'server',
            '<tr><th>'.Adminer\lang(34).'<td>',
            '<input name="auth[server]" value="'.Adminer\h($server).'" title="hostname[:port]" placeholder="localhost" autocapitalize="off">'
        );
        echo Adminer\adminer()->loginFormField(
            'username',
            '<tr><th>'.Adminer\lang(35).'<td>',
            '<input name="auth[username]" id="username" autofocus value="'.Adminer\h($username).'" autocomplete="username" autocapitalize="off">'.
            Adminer\script("const authDriver = qs('#username').form['auth[driver]']; authDriver && authDriver.onchange();")
        );
        echo Adminer\adminer()->loginFormField(
            'password',
            '<tr><th>'.Adminer\lang(36).'<td>',
            '<input type="password" name="auth[password]" autocomplete="current-password">'
        );
        echo Adminer\adminer()->loginFormField(
            'db',
            '<tr><th>'.Adminer\lang(37).'<td>',
            '<input name="auth[db]" value="'.Adminer\h($db).'" autocapitalize="off">'
        );
        echo "</table>\n";
        echo $this->saveAndLoginRow();
        return true;
    }

    private function saveAndLoginRow(): string
    {
        return <<<'HTML'
<p>
    <input type="submit" value="Login" class="saved-connections-launcher" data-saved-connections-save>
</p>
HTML;
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

        $bookmark = $request['bookmark'] ?? null;
        if (is_array($bookmark)) {
            $record['bookmarks'] = $this->upsertBookmark($record['bookmarks'], $bookmark, $now);
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

return new SavedConnectionsPlugin();
