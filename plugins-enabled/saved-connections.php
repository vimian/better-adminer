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

            if ($action === 'delete') {
                $this->requireMethod('POST');
                $payload = $this->readJsonBody();
                $this->deleteConnection($payload);
                $this->sendJson(array('connections' => $this->listConnections()));
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
    border: 1px solid #c7d2df;
    border-radius: 10px;
    padding: 12px;
    margin: 0 0 10px;
    background: linear-gradient(180deg, #ffffff 0%, #f7f9fc 100%);
}
.saved-connections-card__title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 6px;
}
.saved-connections-card__title strong {
    font-size: 13px;
}
.saved-connections-card__hint,
.saved-connections-card__empty,
.saved-connections-card__meta,
.saved-connections-inline-hint,
.saved-connections-modal__hint {
    color: #5a6775;
    font-size: 12px;
    line-height: 1.4;
}
.saved-connections-card__list {
    display: grid;
    gap: 8px;
    margin-top: 10px;
}
.saved-connections-card__item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 10px;
    border: 1px solid #d8e0ea;
    border-radius: 8px;
    background: #fff;
}
.saved-connections-card__open {
    display: block;
    width: 100%;
    text-align: left;
    padding: 0;
    border: 0;
    background: transparent;
    color: inherit;
    cursor: pointer;
}
.saved-connections-card__actions {
    display: flex;
    gap: 8px;
    align-items: center;
}
.saved-connections-card__forget,
.saved-connections-launcher {
    border-radius: 8px;
    border: 1px solid #93a8bf;
    background: #f2f6fb;
    color: #17324d;
    cursor: pointer;
    padding: 8px 12px;
    font: inherit;
}
.saved-connections-launcher {
    margin-right: 8px;
}
.saved-connections-launcher:hover,
.saved-connections-card__forget:hover {
    background: #e5eef8;
}
.saved-connections-floating {
    position: fixed;
    right: 24px;
    bottom: 24px;
    z-index: 1000;
    box-shadow: 0 8px 24px rgba(20, 40, 60, 0.15);
}
.saved-connections-modal {
    position: fixed;
    inset: 0;
    background: rgba(10, 20, 30, 0.55);
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
    width: min(420px, 100%);
    border-radius: 14px;
    background: #fff;
    padding: 20px;
    box-shadow: 0 18px 60px rgba(10, 20, 30, 0.25);
}
.saved-connections-modal__title {
    margin: 0 0 6px;
    font-size: 18px;
}
.saved-connections-modal__description {
    margin: 0 0 16px;
    color: #334155;
    font-size: 13px;
    line-height: 1.5;
}
.saved-connections-modal__field {
    display: grid;
    gap: 6px;
    margin-bottom: 12px;
}
.saved-connections-modal__field label {
    font-weight: 600;
}
.saved-connections-modal__field input {
    width: 100%;
    box-sizing: border-box;
}
.saved-connections-modal__pin {
    display: flex;
    gap: 10px;
}
.saved-connections-modal__pin-slot {
    width: 52px;
    height: 52px;
    border: 1px solid #93a8bf;
    border-radius: 10px;
    text-align: center;
    font-size: 24px;
    line-height: 1;
    padding: 0;
}
.saved-connections-modal__pin-slot:focus {
    outline: 2px solid #17324d;
    outline-offset: 2px;
}
.saved-connections-modal__actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 12px;
}
.saved-connections-modal__submit {
    border-radius: 8px;
    border: 1px solid #17324d;
    background: #17324d;
    color: #fff;
    cursor: pointer;
    padding: 8px 14px;
    font: inherit;
}
.saved-connections-modal__submit:hover {
    background: #0f2740;
}
.saved-connections-modal__error {
    min-height: 20px;
    color: #b42318;
    font-size: 12px;
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
    .saved-connections-card__item {
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
    .saved-connections-modal__pin-slot {
        width: calc((100% - 30px) / 4);
    }
}
</style>
HTML;

        $basePath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        $currentEndpoint = $basePath;
        if (defined('Adminer\\DRIVER') && defined('Adminer\\SERVER') && !empty($_GET['username'])) {
            $query = array(
                (string) constant('Adminer\\DRIVER') => (string) constant('Adminer\\SERVER'),
                'username' => (string) $_GET['username'],
            );
            if (defined('Adminer\\DB') && constant('Adminer\\DB') !== '') {
                $query['db'] = (string) constant('Adminer\\DB');
            }
            $currentEndpoint .= '?'.http_build_query($query);
        }

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
    let toastTimer = null;
    let modalState = null;

    document.addEventListener("DOMContentLoaded", () => {
        injectCurrentConnectionButton();
        void refreshConnections();
    });

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
        } catch (error) {
            renderSavedConnections(error.message);
        }
    }

    function renderSavedConnections(errorMessage = "") {
        const list = document.querySelector("[data-saved-connections-list]");
        if (!list) {
            return;
        }

        list.textContent = "";

        if (errorMessage) {
            const error = document.createElement("div");
            error.className = "saved-connections-card__empty";
            error.textContent = errorMessage;
            list.appendChild(error);
            return;
        }

        if (!cachedConnections.length) {
            const empty = document.createElement("div");
            empty.className = "saved-connections-card__empty";
            empty.textContent = "No saved connections yet. Use Save + Login the first time you connect.";
            list.appendChild(empty);
            return;
        }

        for (const connection of cachedConnections) {
            const item = document.createElement("div");
            item.className = "saved-connections-card__item";

            const openButton = document.createElement("button");
            openButton.type = "button";
            openButton.className = "saved-connections-card__open";
            openButton.innerHTML =
                `<strong>${escapeHtml(connection.label || "Saved connection")}</strong>` +
                `<div class="saved-connections-card__meta">Updated ${formatDate(connection.updatedAt)}</div>`;
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
                openSaveModal(connection, () => authForm.submit());
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
        launcher.textContent = "Save current connection";
        launcher.addEventListener("click", async () => {
            try {
                const data = await apiRequest("current", { useCurrentContext: true });
                openSaveModal(data.connection);
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

    function openSaveModal(connection, onSuccess) {
        const suggestedLabel = [connection.server, connection.db, connection.username]
            .filter(Boolean)
            .join(" / ");
        openModal({
            title: "Save encrypted connection",
            description: "The container stores only encrypted connection data. Choose any 4-character PIN. The PIN is never stored.",
            showLabelField: true,
            defaultLabel: suggestedLabel,
            submit: async ({ label, pin }) => {
                const payload = await encryptConnection(connection, pin);
                await apiRequest("save", {
                    method: "POST",
                    body: JSON.stringify({ label, payload }),
                });
                await refreshConnections();
                showToast(`Saved "${label}".`);
                if (typeof onSuccess === "function") {
                    onSuccess();
                }
            },
        });
    }

    function unlockAndLogin(record) {
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
                writeAuthForm(authForm, decrypted);
                authForm.submit();
            },
        });
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
        $row = $heading.$input."\n";

        if ($name === 'driver') {
            return $this->savedConnectionsPanel().$row;
        }

        return $row;
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

    private function savedConnectionsPanel(): string
    {
        return <<<'HTML'
<tr>
    <td colspan="2">
        <div class="saved-connections-card">
            <div class="saved-connections-card__title">
                <strong>Saved connections</strong>
            </div>
            <div class="saved-connections-card__list" data-saved-connections-list>
                <div class="saved-connections-card__empty">Loading saved connections...</div>
            </div>
        </div>
    </td>
</tr>
HTML;
    }

    private function saveAndLoginRow(): string
    {
        return <<<'HTML'
<p>
    <button type="submit" class="saved-connections-launcher" data-saved-connections-save>Save + Login</button>
    <span class="saved-connections-inline-hint">Saves this connection encrypted, then signs in with the same details.</span>
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

        $store = $this->loadStore();
        $now = gmdate(DATE_ATOM);
        $existingIndex = null;
        foreach ($store['connections'] as $index => $connection) {
            if (strcasecmp((string) $connection['label'], $label) === 0) {
                $existingIndex = $index;
                break;
            }
        }

        $record = array(
            'id' => $existingIndex === null ? bin2hex(random_bytes(12)) : $store['connections'][$existingIndex]['id'],
            'label' => $label,
            'payload' => $payload,
            'createdAt' => $existingIndex === null ? $now : $store['connections'][$existingIndex]['createdAt'],
            'updatedAt' => $now,
        );

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

    private function getCurrentConnection(): array
    {
        $password = Adminer\get_password();
        $username = (string) ($_GET['username'] ?? '');

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

    private function loadStore(): array
    {
        $this->ensureStorageDirectory();

        if (!is_file($this->storageFile)) {
            return array('version' => 1, 'connections' => array());
        }

        $json = file_get_contents($this->storageFile);
        $decoded = json_decode($json ?: '', true);

        if (!is_array($decoded) || !isset($decoded['connections']) || !is_array($decoded['connections'])) {
            return array('version' => 1, 'connections' => array());
        }

        return $decoded;
    }

    private function writeStore(array $store): void
    {
        $this->ensureStorageDirectory();

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
}

return new SavedConnectionsPlugin();
