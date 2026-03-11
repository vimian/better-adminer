(() => {
    const config = window.AdminerSavedConnectionsConfig;
    if (!config || !window.crypto?.subtle) {
        return;
    }

    const savedConnections = window.AdminerSavedConnections || {};
    savedConnections.config = config;
    savedConnections.encoder = savedConnections.encoder || new TextEncoder();
    savedConnections.decoder = savedConnections.decoder || new TextDecoder();
    savedConnections.state = savedConnections.state || {
        cachedConnections: [],
        currentConnection: null,
        currentFingerprint: "",
        currentConnectionError: "",
        toastTimer: null,
        modalState: null,
    };

    savedConnections.endpointUrl = function endpointUrl(action, useCurrentContext = false) {
        const url = new URL(useCurrentContext ? config.currentEndpoint : config.endpoint, window.location.origin);
        url.searchParams.set(config.apiParam, "1");
        url.searchParams.set("action", action);
        return url;
    };

    savedConnections.apiRequest = async function apiRequest(action, options = {}) {
        const headers = new Headers(options.headers || {});
        headers.set("X-Adminer-Token", config.token);
        if (options.body && !headers.has("Content-Type")) {
            headers.set("Content-Type", "application/json");
        }

        const response = await fetch(savedConnections.endpointUrl(action, options.useCurrentContext === true), {
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
    };

    savedConnections.refreshConnections = async function refreshConnections() {
        try {
            const data = await savedConnections.apiRequest("list");
            savedConnections.state.cachedConnections = Array.isArray(data.connections) ? data.connections : [];
            savedConnections.renderSavedConnections();
            savedConnections.renderBookmarks();
        } catch (error) {
            savedConnections.renderSavedConnections(error.message);
            savedConnections.renderBookmarks(error.message);
        }
    };

    savedConnections.findConnectionByFingerprint = function findConnectionByFingerprint(fingerprint) {
        if (!fingerprint) {
            return null;
        }

        return savedConnections.state.cachedConnections.find(connection => connection.fingerprint === fingerprint) || null;
    };

    savedConnections.formatDate = function formatDate(value) {
        if (!value) {
            return "recently";
        }

        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return "recently";
        }

        return date.toLocaleString();
    };

    savedConnections.formatCount = function formatCount(value, singular) {
        const count = Number(value) || 0;
        return `${count} ${singular}${count === 1 ? "" : "s"}`;
    };

    savedConnections.escapeHtml = function escapeHtml(value) {
        return String(value)
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#39;");
    };

    savedConnections.showToast = function showToast(message) {
        let toast = document.querySelector("[data-saved-connections-toast]");
        if (!toast) {
            toast = document.createElement("div");
            toast.className = "saved-connections-toast";
            toast.dataset.savedConnectionsToast = "1";
            document.body.appendChild(toast);
        }

        toast.textContent = message;
        toast.hidden = false;
        if (savedConnections.state.toastTimer) {
            window.clearTimeout(savedConnections.state.toastTimer);
        }
        savedConnections.state.toastTimer = window.setTimeout(() => {
            toast.hidden = true;
        }, 2500);
    };

    savedConnections.initializePageState = async function initializePageState() {
        if (!savedConnections.getAuthForm()) {
            try {
                await savedConnections.ensureCurrentConnection();
            } catch (error) {
                // renderBookmarks handles the failure state.
            }
        }

        await savedConnections.refreshConnections();
    };

    window.AdminerSavedConnections = savedConnections;
    document.addEventListener("DOMContentLoaded", () => {
        savedConnections.injectSavedConnectionsPanel();
        savedConnections.injectBookmarksPanel();
        savedConnections.injectCurrentConnectionButton();
        void savedConnections.initializePageState();
    });
})();
