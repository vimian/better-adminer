(() => {
    const savedConnections = window.AdminerSavedConnections;
    if (!savedConnections) {
        return;
    }

    const { formatCount, formatDate, escapeHtml, showToast } = savedConnections;

    savedConnections.injectSavedConnectionsPanel = function injectSavedConnectionsPanel() {
        if (!savedConnections.getAuthForm() || document.querySelector("[data-saved-connections-panel]")) {
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
    };

    savedConnections.renderSavedConnections = function renderSavedConnections(errorMessage = "") {
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

        if (!savedConnections.state.cachedConnections.length) {
            panel.hidden = true;
            return;
        }

        panel.hidden = false;
        for (const connection of savedConnections.state.cachedConnections) {
            const item = document.createElement("div");
            item.className = "saved-connections-card__item";

            const openButton = document.createElement("button");
            openButton.type = "button";
            openButton.className = "saved-connections-card__open";
            openButton.innerHTML =
                `<strong class="saved-connections-card__title">${escapeHtml(connection.label || "Saved connection")}</strong>` +
                `<div class="saved-connections-card__meta">Updated ${formatDate(connection.updatedAt)} - ${formatCount(connection.bookmarks?.length || 0, "bookmark")}</div>`;
            openButton.addEventListener("click", () => savedConnections.unlockAndLogin(connection));

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
                    await savedConnections.apiRequest("delete", {
                        method: "POST",
                        body: JSON.stringify({ id: connection.id }),
                    });
                    showToast("Connection removed.");
                    await savedConnections.refreshConnections();
                } catch (error) {
                    showToast(error.message);
                }
            });

            actions.appendChild(forgetButton);
            item.append(openButton, actions);
            list.appendChild(item);
        }
    };
})();
