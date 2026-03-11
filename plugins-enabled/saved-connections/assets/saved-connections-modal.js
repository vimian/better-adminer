(() => {
    const savedConnections = window.AdminerSavedConnections;
    if (!savedConnections) {
        return;
    }

    savedConnections.openModal = function openModal(options) {
        savedConnections.closeModal();

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
        let labelInput = null;

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
                savedConnections.closeModal();
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

        form.addEventListener("submit", event => {
            event.preventDefault();
            void trySubmit();
        });

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
        savedConnections.state.modalState = { overlay };

        overlay.addEventListener("click", event => {
            if (event.target === overlay) {
                savedConnections.closeModal();
            }
        });

        document.addEventListener("keydown", savedConnections.onModalKeydown, { once: true });
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
                    savedConnections.closeModal();
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
                pinInputs[Math.min(pasted.length, pinInputs.length - 1)].focus();
                if (pinInputs.every(item => item.value.length === 1)) {
                    void trySubmit();
                }
            });
        });

        if (labelInput) {
            labelInput.addEventListener("keydown", event => {
                if (event.key === "Escape") {
                    savedConnections.closeModal();
                } else if (event.key === "Enter") {
                    event.preventDefault();
                    pinInputs[0].focus();
                }
            });
            (labelInput.value ? pinInputs[0] : labelInput).focus();
            return;
        }

        pinInputs[0].focus();
    };

    savedConnections.onModalKeydown = function onModalKeydown(event) {
        if (event.key === "Escape") {
            savedConnections.closeModal();
            return;
        }

        document.addEventListener("keydown", savedConnections.onModalKeydown, { once: true });
    };

    savedConnections.closeModal = function closeModal() {
        if (!savedConnections.state.modalState) {
            return;
        }

        savedConnections.state.modalState.overlay.remove();
        savedConnections.state.modalState = null;
    };
})();
