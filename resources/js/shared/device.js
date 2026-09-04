const KEY = 'sos.device_token';

/** The phone's identity across sessions. Created once, kept in localStorage forever. */
export function deviceToken() {
    try {
        let token = localStorage.getItem(KEY);
        if (!token) {
            token = crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(16).slice(2)}`;
            localStorage.setItem(KEY, token);
        }
        return token;
    } catch {
        return `ephemeral-${Math.random().toString(16).slice(2)}`;
    }
}

export function remember(key, value) {
    try {
        if (value === null || value === undefined) localStorage.removeItem(`sos.${key}`);
        else localStorage.setItem(`sos.${key}`, String(value));
    } catch {
        /* private mode etc. */
    }
}

export function recall(key) {
    try {
        return localStorage.getItem(`sos.${key}`);
    } catch {
        return null;
    }
}
