(() => {
    const savedConnections = window.AdminerSavedConnections;
    if (!savedConnections) {
        return;
    }

    savedConnections.getAuthForm = function getAuthForm() {
        return document.querySelector("input[name='auth[server]']")?.form || null;
    };

    savedConnections.readAuthForm = function readAuthForm(form) {
        return {
            driver: form.elements["auth[driver]"]?.value || "",
            server: form.elements["auth[server]"]?.value.trim() || "",
            username: form.elements["auth[username]"]?.value.trim() || "",
            password: form.elements["auth[password]"]?.value || "",
            db: form.elements["auth[db]"]?.value.trim() || "",
        };
    };

    savedConnections.writeAuthForm = function writeAuthForm(form, connection) {
        form.elements["auth[driver]"].value = connection.driver || "server";
        form.elements["auth[server]"].value = connection.server || "";
        form.elements["auth[username]"].value = connection.username || "";
        form.elements["auth[password]"].value = connection.password || "";
        form.elements["auth[db]"].value = connection.db || "";
    };

    savedConnections.injectCurrentConnectionButton = function injectCurrentConnectionButton() {
        const authForm = savedConnections.getAuthForm();
        if (authForm) {
            const saveButton = document.querySelector("[data-saved-connections-save]");
            const beginSaveAndLogin = event => {
                event.preventDefault();
                const connection = savedConnections.readAuthForm(authForm);
                if (!connection.server || !connection.username || !connection.password) {
                    savedConnections.showToast("Fill in server, username, and password first.");
                    return;
                }

                void savedConnections.saveConnectionAndLogin(connection, () => authForm.submit());
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
                await savedConnections.saveCurrentPage();
            } catch (error) {
                savedConnections.showToast(error.message);
            }
        });
        document.body.appendChild(launcher);
    };

    savedConnections.saveConnectionAndLogin = async function saveConnectionAndLogin(connection, onSuccess = null, initialBookmark = null) {
        const fingerprint = await savedConnections.fingerprintConnection(connection);
        const existingConnection = savedConnections.findConnectionByFingerprint(fingerprint);
        if (existingConnection && !initialBookmark) {
            if (typeof onSuccess === "function") {
                onSuccess();
            }
            return;
        }

        const suggestedLabel = [connection.server, connection.db, connection.username].filter(Boolean).join(" / ");
        savedConnections.openModal({
            title: initialBookmark ? "Save encrypted connection and bookmark" : "Save encrypted connection",
            description: initialBookmark
                ? "The container stores only encrypted connection data. Choose any 4-character PIN to save these credentials and this page bookmark together."
                : "The container stores only encrypted connection data. Choose any 4-character PIN. The PIN is never stored.",
            showLabelField: !existingConnection,
            defaultLabel: existingConnection?.label || suggestedLabel,
            submit: async ({ label, pin }) => {
                const payload = await savedConnections.encryptConnection(connection, pin);
                const saveLabel = (label || existingConnection?.label || suggestedLabel).trim();
                await savedConnections.apiRequest("save", {
                    method: "POST",
                    body: JSON.stringify({
                        label: saveLabel,
                        payload,
                        fingerprint,
                        bookmark: initialBookmark,
                    }),
                });
                await savedConnections.refreshConnections();
                savedConnections.showToast(initialBookmark ? `Saved "${saveLabel}" and bookmarked this page.` : `Saved "${saveLabel}".`);
                if (typeof onSuccess === "function") {
                    onSuccess();
                }
            },
        });
    };

    savedConnections.unlockAndLogin = async function unlockAndLogin(record) {
        const authForm = savedConnections.getAuthForm();
        if (!authForm) {
            savedConnections.showToast("Open a login page to use a saved connection.");
            return;
        }

        savedConnections.openModal({
            title: `Unlock ${record.label}`,
            description: "Enter the 4-character PIN for this connection.",
            showLabelField: false,
            submit: async ({ pin }) => {
                const decrypted = await savedConnections.decryptConnection(record.payload, pin);
                const fingerprint = await savedConnections.fingerprintConnection(decrypted);
                if (record.id && fingerprint && record.fingerprint !== fingerprint) {
                    await savedConnections.apiRequest("link_connection", {
                        method: "POST",
                        body: JSON.stringify({ id: record.id, fingerprint }),
                    });
                }
                savedConnections.writeAuthForm(authForm, decrypted);
                authForm.submit();
            },
        });
    };
})();
