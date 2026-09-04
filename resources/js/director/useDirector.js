import { computed, ref } from 'vue';
import axios from 'axios';
import { recall, remember } from '../shared/device';

/**
 * The director's identity and API. One shared password (CONTRACT.md § 2), kept in
 * `sos.director_key` and sent as X-Director-Key on every request and on channel auth.
 * A 401 anywhere clears the key, which drops the panel back to the password gate.
 */
const key = ref(recall('director_key') ?? '');

export class CommandError extends Error {
    constructor(status, body) {
        super(body?.message ?? `Request failed (${status})`);
        this.status = status;
        this.reason = body?.reason ?? (status === 0 ? 'network' : `http_${status}`);
    }
}

function client(withKey = true) {
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    if (withKey && key.value) headers['X-Director-Key'] = key.value;
    return axios.create({ baseURL: '/api/director', headers });
}

async function call(fn) {
    try {
        const { data } = await fn(client());
        return data;
    } catch (error) {
        const status = error?.response?.status ?? 0;
        if (status === 401) {
            key.value = '';
            remember('director_key', null);
        }
        throw new CommandError(status, error?.response?.data);
    }
}

export function useDirector() {
    return {
        key,
        authed: computed(() => key.value !== ''),

        async login(password) {
            try {
                await client(false).post('/login', { password });
            } catch (error) {
                const status = error?.response?.status ?? 0;
                if (status === 401) throw new CommandError(401, { message: 'Wrong password.', reason: 'wrong_password' });
                throw new CommandError(status, error?.response?.data ?? { message: "Can't reach the server." });
            }
            key.value = password;
            remember('director_key', password);
        },
        logout() {
            key.value = '';
            remember('director_key', null);
        },

        sessions: () => call((c) => c.get('/sessions')),
        create: (body) => call((c) => c.post('/sessions', body)),
        session: (code) => call((c) => c.get(`/sessions/${code}`)),
        analysis: (code) => call((c) => c.get(`/sessions/${code}/analysis`)),
        /** start | pause | resume | next | end | screen/reload */
        command: (code, action) => call((c) => c.post(`/sessions/${code}/${action}`)),
        admit: (code, id) => call((c) => c.post(`/sessions/${code}/players/${id}/admit`)),
        kick: (code, id) => call((c) => c.post(`/sessions/${code}/players/${id}/kick`)),
    };
}
