import { defineStore } from 'pinia';
import { createApi, identityHeaders } from '../api';
import { createEcho } from '../echo';
import { createClock } from '../clock';

/**
 * The one store every entry point uses. It knows the session code, who this client is,
 * the latest `state` object (CONTRACT.md § State), and a log of every event received.
 *
 * Later sessions add richer, purpose-specific state (the current pairing, the reveal,
 * the analysis card) as actions here or in per-app stores that build on this one.
 */
export const useGameStore = defineStore('game', {
    state: () => ({
        code: null,
        /** 'phone' | 'screen' | 'director' */
        kind: null,
        identity: { deviceToken: null, directorKey: null, screenCode: null },
        connection: 'idle', // idle | connecting | connected | unavailable | failed
        state: null, // the last `state` object seen
        players: [], // from GET /api/sessions/{code}
        me: null, // the player record for this phone, once joined (Session 2)
        events: [], // newest first, capped
        channels: [], // channel names we are subscribed to
        lastError: null,
        _echo: null,
        _api: null,
        _clock: createClock(),
    }),

    getters: {
        api: (s) => s._api,
        clock: (s) => s._clock,
        isPaused: (s) => Boolean(s.state?.paused),
        status: (s) => s.state?.status ?? null,
    },

    actions: {
        /**
         * Configure identity and connect. `kind` decides which channels we subscribe to:
         *   phone    -> session.{code}, and player.{id} once `me` is known
         *   screen   -> session.{code}, screen.{code}
         *   director -> session.{code}, screen.{code}, director.{code}
         */
        async connect({ code, kind, deviceToken = null, directorKey = null }) {
            this.code = String(code).toUpperCase();
            this.kind = kind;
            this.identity = {
                deviceToken,
                directorKey,
                // A screen identifies itself by the code; phones fall back to it before they have joined.
                screenCode: kind === 'director' ? null : this.code,
            };

            const headers = identityHeaders({ ...this.identity, sessionCode: this.code });
            this._api = createApi({ ...this.identity, sessionCode: this.code });

            await this.fetchState();

            this.disconnect();
            this._echo = createEcho(headers);
            this.connection = 'connecting';

            const pusher = this._echo.connector.pusher;
            pusher.connection.bind('state_change', ({ current }) => {
                this.connection = current;
            });

            this.subscribe(`session.${this.code}`);
            if (kind === 'screen' || kind === 'director') this.subscribe(`screen.${this.code}`);
            if (kind === 'director') this.subscribe(`director.${this.code}`);
            if (this.me?.id) this.subscribe(`player.${this.me.id}`);
        },

        subscribe(channel) {
            if (!this._echo || this.channels.includes(channel)) return;
            this._echo
                .private(channel)
                .listenToAll((name, payload) => this.receive(channel, name, payload))
                .error((error) => {
                    this.lastError = `${channel}: ${error?.status ?? ''} ${error?.error ?? 'subscription failed'}`.trim();
                });
            this.channels.push(channel);
        },

        /** Every broadcast lands here. Envelope first, then per-event handling (later sessions). */
        receive(channel, name, payload) {
            const event = name.replace(/^\./, '');
            if (payload?.server_time) this._clock.sync(payload.server_time);
            if (payload?.state) this.state = payload.state;
            this.events.unshift({ at: new Date().toISOString(), channel, event, payload });
            if (this.events.length > 200) this.events.length = 200;
        },

        async fetchState() {
            try {
                const { data } = await this._api.get(`/sessions/${this.code}`);
                this._clock.sync(data.server_time);
                this.state = data.state;
                this.players = data.players ?? [];
                this.lastError = null;
            } catch (error) {
                this.lastError = error?.response?.status === 404 ? `No session with code ${this.code}` : String(error);
                throw error;
            }
        },

        disconnect() {
            if (this._echo) {
                this._echo.disconnect();
                this._echo = null;
            }
            this.channels = [];
            this.connection = 'idle';
        },
    },
});
