(() => {
    const savedConnections = window.AdminerSavedConnections;
    if (!savedConnections) {
        return;
    }

    const { decoder, encoder } = savedConnections;

    savedConnections.encryptConnection = async function encryptConnection(connection, pin) {
        const salt = crypto.getRandomValues(new Uint8Array(16));
        const iv = crypto.getRandomValues(new Uint8Array(12));
        const key = await savedConnections.deriveKey(pin, salt, ["encrypt"]);
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
            salt: savedConnections.bytesToBase64(salt),
            iv: savedConnections.bytesToBase64(iv),
            ciphertext: savedConnections.bytesToBase64(new Uint8Array(ciphertext)),
        };
    };

    savedConnections.decryptConnection = async function decryptConnection(payload, pin) {
        if (!payload || !payload.salt || !payload.iv || !payload.ciphertext) {
            throw new Error("Stored connection is incomplete.");
        }

        const salt = savedConnections.base64ToBytes(payload.salt);
        const iv = savedConnections.base64ToBytes(payload.iv);
        const ciphertext = savedConnections.base64ToBytes(payload.ciphertext);
        const key = await savedConnections.deriveKey(pin, salt, ["decrypt"], payload.iterations || 250000);
        const plaintext = await crypto.subtle.decrypt({ name: "AES-GCM", iv }, key, ciphertext);
        return JSON.parse(decoder.decode(plaintext));
    };

    savedConnections.deriveKey = async function deriveKey(pin, salt, usages, iterations = 250000) {
        const keyMaterial = await crypto.subtle.importKey("raw", encoder.encode(pin), "PBKDF2", false, ["deriveKey"]);
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
    };

    savedConnections.fingerprintConnection = async function fingerprintConnection(connection) {
        const normalized = JSON.stringify({
            driver: connection?.driver || "",
            server: connection?.server || "",
            username: connection?.username || "",
            db: connection?.db || "",
        });
        const digest = await crypto.subtle.digest("SHA-256", encoder.encode(normalized));
        return Array.from(new Uint8Array(digest), byte => byte.toString(16).padStart(2, "0")).join("");
    };

    savedConnections.bytesToBase64 = function bytesToBase64(bytes) {
        let binary = "";
        bytes.forEach(byte => {
            binary += String.fromCharCode(byte);
        });
        return window.btoa(binary);
    };

    savedConnections.base64ToBytes = function base64ToBytes(value) {
        const binary = window.atob(value);
        const bytes = new Uint8Array(binary.length);
        for (let index = 0; index < binary.length; index += 1) {
            bytes[index] = binary.charCodeAt(index);
        }
        return bytes;
    };
})();
