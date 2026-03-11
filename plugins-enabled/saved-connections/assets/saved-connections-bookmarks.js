(() => {
    const savedConnections = window.AdminerSavedConnections;
    if (!savedConnections) {
        return;
    }

    const { formatDate, escapeHtml, showToast } = savedConnections;

    savedConnections.injectBookmarksPanel = function injectBookmarksPanel() {
        if (savedConnections.getAuthForm() || document.querySelector("[data-saved-bookmarks-panel]")) {
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
    };

    savedConnections.renderBookmarks = function renderBookmarks(errorMessage = "") {
        const panel = document.querySelector("[data-saved-bookmarks-panel]");
        const subtitle = document.querySelector("[data-saved-bookmarks-subtitle]");
        const list = document.querySelector("[data-saved-bookmarks-list]");
        if (!panel || !subtitle || !list) {
            return;
        }

        list.textContent = "";
        if (errorMessage || savedConnections.state.currentConnectionError) {
            panel.hidden = false;
            subtitle.textContent = "";
            const error = document.createElement("div");
            error.className = "saved-connections-card__empty";
            error.textContent = errorMessage || savedConnections.state.currentConnectionError;
            list.appendChild(error);
            return;
        }

        if (!savedConnections.state.currentFingerprint) {
            panel.hidden = true;
            return;
        }

        panel.hidden = false;
        const connection = savedConnections.findConnectionByFingerprint(savedConnections.state.currentFingerprint);
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
            openButton.innerHTML = `<strong>${escapeHtml(bookmark.label || "Bookmark")}</strong>`;
            openButton.addEventListener("click", () => savedConnections.openBookmarkInBackground(bookmark.url));

            const label = openButton.querySelector("strong");
            if (label) {
                label.className = "saved-connections-bookmarks__label";
                label.textContent = savedConnections.getBookmarkDisplayLabel(bookmark, connection);
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
                    await savedConnections.apiRequest("delete_bookmark", {
                        method: "POST",
                        body: JSON.stringify({
                            connectionId: connection.id,
                            bookmarkId: bookmark.id,
                        }),
                    });
                    showToast("Bookmark removed.");
                    await savedConnections.refreshConnections();
                } catch (error) {
                    showToast(error.message);
                }
            });

            actions.appendChild(forgetButton);
            item.append(openButton, meta, actions);
            list.appendChild(item);
        }
    };
})();
