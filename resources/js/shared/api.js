import axios from 'axios';

/**
 * One axios instance per client, carrying the identity headers from CONTRACT.md § Identity.
 * Pass whichever credentials this client has; the server picks the strongest.
 */
export function createApi({ deviceToken, directorKey, screenCode, sessionCode } = {}) {
    const headers = { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' };
    if (deviceToken) headers['X-Device-Token'] = deviceToken;
    if (directorKey) headers['X-Director-Key'] = directorKey;
    if (screenCode) headers['X-Screen-Code'] = screenCode;
    if (sessionCode) headers['X-Session-Code'] = sessionCode;

    return axios.create({ baseURL: '/api', headers });
}

export function identityHeaders({ deviceToken, directorKey, screenCode, sessionCode } = {}) {
    const headers = {};
    if (deviceToken) headers['X-Device-Token'] = deviceToken;
    if (directorKey) headers['X-Director-Key'] = directorKey;
    if (screenCode) headers['X-Screen-Code'] = screenCode;
    if (sessionCode) headers['X-Session-Code'] = sessionCode;
    return headers;
}
