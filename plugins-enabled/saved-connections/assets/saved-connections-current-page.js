(() => {
    const savedConnections = window.AdminerSavedConnections;
    if (!savedConnections) {
        return;
    }

    savedConnections.ensureCurrentConnection = async function ensureCurrentConnection() {
        if (savedConnections.state.currentConnection || savedConnections.getAuthForm()) {
            return savedConnections.state.currentConnection;
        }

        try {
            const data = await savedConnections.apiRequest("current", { useCurrentContext: true });
            savedConnections.state.currentConnection = data.connection || null;
            savedConnections.state.currentFingerprint = savedConnections.state.currentConnection
                ? await savedConnections.fingerprintConnection(savedConnections.state.currentConnection)
                : "";
            savedConnections.state.currentConnectionError = "";
            return savedConnections.state.currentConnection;
        } catch (error) {
            savedConnections.state.currentConnectionError = error.message || "Unable to inspect the current connection.";
            throw error;
        }
    };

    savedConnections.saveCurrentPage = async function saveCurrentPage() {
        const connection = await savedConnections.ensureCurrentConnection();
        if (!connection) {
            throw new Error("No active connection is available to save.");
        }

        const bookmark = savedConnections.getCurrentBookmark(connection);
        const fingerprint = savedConnections.state.currentFingerprint || await savedConnections.fingerprintConnection(connection);
        const existingConnection = savedConnections.findConnectionByFingerprint(fingerprint);
        if (!existingConnection) {
            await savedConnections.saveConnectionAndLogin(connection, null, bookmark);
            return;
        }

        await savedConnections.apiRequest("save_bookmark", {
            method: "POST",
            body: JSON.stringify({ fingerprint, bookmark }),
        });
        await savedConnections.refreshConnections();
        savedConnections.showToast(`Bookmarked "${bookmark.label}".`);
    };

    savedConnections.getCurrentBookmark = function getCurrentBookmark(connection) {
        const relativeUrl = `${window.location.pathname}${window.location.search}${window.location.hash}`;
        const headingText =
            document.querySelector("#breadcrumb")?.textContent?.replace(/\s+/g, " ").trim() ||
            document.querySelector("h2")?.textContent?.trim() ||
            "";
        const titleText = savedConnections.cleanBookmarkLabel(document.title);
        const label = savedConnections.getBookmarkUrlSuffix(relativeUrl, connection) || headingText || titleText || relativeUrl;

        return {
            label: label.slice(0, 120),
            url: relativeUrl,
        };
    };

    savedConnections.getBookmarkDisplayLabel = function getBookmarkDisplayLabel(bookmark, connection) {
        return savedConnections.getBookmarkUrlSuffix(bookmark.url || "", connection) ||
            savedConnections.cleanBookmarkLabel(bookmark.label || "") ||
            savedConnections.cleanBookmarkLabel(bookmark.url || "") ||
            "Bookmark";
    };

    savedConnections.getBookmarkUrlSuffix = function getBookmarkUrlSuffix(url, connection) {
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
    };

    savedConnections.cleanBookmarkLabel = function cleanBookmarkLabel(value) {
        return String(value || "")
            .replace(/\bAdminer\s*\d[\d.]*\b/gi, "")
            .replace(/\s*-\s*Adminer\s*$/i, "")
            .replace(/\s+Adminer\s*$/i, "")
            .replace(/\s+/g, " ")
            .trim();
    };

    savedConnections.openBookmarkInBackground = function openBookmarkInBackground(url) {
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
    };
})();
