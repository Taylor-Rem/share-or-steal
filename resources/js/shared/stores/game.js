import { defineStore } from 'pinia';
import { createApi, identityHeaders } from '../api';
import { createEcho } from '../echo';
import { createClock } from '../clock';

/**
 * The one store every entry point uses. It knows the session code, who this client is,
 * the latest `state` object (CONTRACT.md § 5), and everything the contract's events
 * have told it since. Nothing here is computed; the server sends it all.
 *
 * Phones: connectPhone() reconnects from `GET me` (§ 12), then events keep it current.
 * Screen and director: connect() as before; Sessions 3 and 4 build on the public fields.
 */
export const useGameStore = defineStore('game', {
    state: () => ({
        code: null,
        /** 'phone' | 'screen' | 'director' */
        kind: null,
        identity: { deviceToken: null, directorKey: null, screenCode: null },
        connection: 'idle', // idle | connecting | connected | unavailable | failed | fixture
        state: null, // the last `state` object seen
        players: [], // from GET /api/sessions/{code}, kept current by player.joined / player.left

        // --- this phone (CONTRACT.md § 9.2 and GET me) ---
        me: null, // PublicPlayer, once joined
        isAdmitted: true,
        kicked: false,
        totalPoints: 0,
        round: null, // { number, anonymous, decisions_per_round, seat, partner }
        decision: null, // { round, index, opened_at, deadline_at, choose_ms, your_choice, chosen, rejected }
        reveals: {}, // decision index -> you.revealed payload, this round
        lastReveal: null,
        roundSummary: null, // you.round_summary
        nudge: null, // consecutive_timeouts from you.nudged, until the next decision opens
        card: null, // the latest you.card
        cards: [], // every you.card this game, oldest first
        ended: null, // session.ended reason

        // --- the room (CONTRACT.md § 9.1) ---
        pairs: [], // pairing.revealed
        publicReveal: null, // decision.revealed
        publicSummary: null, // round.summary
        beat: null, // analysis.beat

        events: [], // newest first, capped
        channels: [], // channel names we are subscribed to
        lastError: null,
        _echo: null,
        _api: null,
        _clock: createClock(),
        _fixture: null, // set by useFixture(); choices go there instead of the server
    }),

    getters: {
        api: (s) => s._api,
        clock: (s) => s._clock,
        isPaused: (s) => Boolean(s.state?.paused),
        status: (s) => s.state?.status ?? null,
        joined: (s) => Boolean(s.me),
        partner: (s) => s.round?.partner ?? null,
        decisionsPerRound: (s) => s.round?.decisions_per_round ?? s.state?.decisions_per_round ?? 10,
        /** True while the current round on the server is one this phone was paired for. */
        inCurrentRound: (s) => Boolean(s.round && s.state && s.round.number === s.state.round),
        /** Your running total this round, from the last reveal. */
        roundTotal: (s) => s.lastReveal?.round_total ?? { you: 0, partner: 0 },
        isFixture: (s) => Boolean(s._fixture),
    },

    actions: {
        /** Set identity and build the API client. No network. */
        configure({ code, kind, deviceToken = null, directorKey = null }) {
            this.code = String(code).toUpperCase();
            this.kind = kind;
            this.identity = {
                deviceToken,
                directorKey,
                // A screen identifies itself by the code; phones fall back to it before they have joined.
                screenCode: kind === 'director' ? null : this.code,
            };
            this._api = createApi({ ...this.identity, sessionCode: this.code });
        },

        /**
         * Screen and director: load the public snapshot and subscribe.
         *   screen   -> session.{code}, screen.{code}
         *   director -> session.{code}, screen.{code}, director.{code}
         */
        async connect({ code, kind, deviceToken = null, directorKey = null }) {
            this.configure({ code, kind, deviceToken, directorKey });
            await this.fetchState();
            this.open();
            this.subscribe(`session.${this.code}`);
            if (kind === 'screen' || kind === 'director') this.subscribe(`screen.${this.code}`);
            if (kind === 'director') this.subscribe(`director.${this.code}`);
            if (this.me?.id) this.subscribe(`player.${this.me.id}`);
        },

        /**
         * A phone reconnecting (CONTRACT.md § 12): snapshot from GET me, then subscribe.
         * Returns 'joined' or 'not_joined' (this device has no player here: show the join form).
         */
        async connectPhone({ code, deviceToken }) {
            this.configure({ code, kind: 'phone', deviceToken });
            await this.fetchState();
            try {
                await this.loadMe();
            } catch (error) {
                if (error?.response?.status === 401) return 'not_joined';
                throw error;
            }
            this.open();
            this.subscribe(`player.${this.me.id}`);
            // The session channel refuses a phone that is not admitted (or was kicked); you.admitted opens it.
            if (this.isAdmitted && !this.kicked) this.subscribe(`session.${this.code}`);
            return 'joined';
        },

        /** POST join. Resolves with the response body; rejects with { reason, message } (§ 10.2). */
        async join({ code, username, deviceToken }) {
            this.configure({ code, kind: 'phone', deviceToken });
            try {
                const { data } = await this._api.post(`/sessions/${this.code}/join`, { username, device_token: deviceToken });
                this._clock.sync(data.server_time);
                this.state = data.state;
                this.me = data.player;
                this.isAdmitted = data.is_admitted;
                this.kicked = false;
                this.lastError = null;
                return data;
            } catch (error) {
                throw normalizeJoinError(error);
            }
        },

        async loadMe() {
            const { data } = await this._api.get(`/sessions/${this.code}/me`);
            this.applyMe(data);
            return data;
        },

        /** The GET me snapshot (§ 10.2) into store fields, so the phone can render this instant. */
        applyMe(data) {
            this._clock.sync(data.server_time);
            this.state = data.state;
            this.me = data.player;
            this.isAdmitted = data.is_admitted;
            this.kicked = Boolean(data.kicked);
            this.totalPoints = data.total_points ?? 0;
            this.round = data.round
                ? {
                      number: data.round.number,
                      anonymous: data.round.anonymous,
                      decisions_per_round: data.state.decisions_per_round,
                      seat: data.round.seat,
                      partner: data.round.partner,
                  }
                : null;
            this.decision = data.decision
                ? {
                      round: data.round?.number ?? data.state.round,
                      index: data.decision.index,
                      opened_at: data.decision.opened_at,
                      deadline_at: data.decision.deadline_at,
                      choose_ms: null,
                      your_choice: data.decision.your_choice,
                      chosen: data.decision.chosen,
                      rejected: null,
                  }
                : null;
            this.lastReveal = data.last_reveal ?? null;
            this.reveals = data.last_reveal ? { [data.last_reveal.decision]: data.last_reveal } : {};
            this.roundSummary = null;
            this.card = data.card ?? null;
            this.cards = data.card ? [data.card] : [];
            this.ended = data.state.status === 'finished' ? 'completed' : null;
            this.lastError = null;
        },

        /**
         * POST choice. First choice wins on the server; a rejection lands in decision.rejected
         * with the contract's reason. In fixture mode the fixture answers instead.
         */
        async choose(choice) {
            const decision = this.decision;
            if (!decision || decision.chosen) return;
            if (this._fixture) {
                this._fixture.choose(choice);
                return;
            }
            try {
                const { data } = await this._api.post(`/sessions/${this.code}/choice`, {
                    choice,
                    round: decision.round,
                    decision: decision.index,
                });
                this._clock.sync(data.server_time);
                if (this.decision?.index === decision.index) {
                    this.decision.your_choice = data.choice;
                    this.decision.chosen = true;
                }
            } catch (error) {
                const body = error?.response?.data;
                if (body?.server_time) this._clock.sync(body.server_time);
                if (this.decision?.index === decision.index) {
                    this.decision.rejected = body?.reason ?? 'network';
                    if (body?.reason === 'already_chosen') this.decision.chosen = true;
                }
            }
        },

        open() {
            this.disconnect();
            const headers = identityHeaders({ ...this.identity, sessionCode: this.code });
            this._echo = createEcho(headers);
            this.connection = 'connecting';
            this._echo.connector.pusher.connection.bind('state_change', ({ current }) => {
                this.connection = current;
            });
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

        /** Every broadcast lands here: envelope first, then the event itself. */
        receive(channel, name, payload) {
            const event = name.replace(/^\./, '');
            if (payload?.server_time) this._clock.sync(payload.server_time);
            if (payload?.state) this.state = payload.state;
            this.events.unshift({ at: new Date().toISOString(), channel, event, payload });
            if (this.events.length > 200) this.events.length = 200;
            this.apply(event, payload);
        },

        /** Per-event handling, CONTRACT.md § 9. Pure: no network, so it is what the tests drive. */
        apply(event, payload) {
            switch (event) {
                case 'player.joined':
                    if (payload.player && !this.players.some((p) => p.id === payload.player.id)) this.players.push(payload.player);
                    break;
                case 'player.left':
                    this.players = this.players.filter((p) => p.id !== payload.player_id);
                    if (this.me && payload.player_id === this.me.id) this.kicked = true;
                    break;
                case 'pairing.revealed':
                    this.pairs = payload.pairs ?? [];
                    this.publicSummary = null;
                    this.publicReveal = null;
                    break;
                case 'you.paired':
                    this.round = {
                        number: payload.round,
                        anonymous: payload.anonymous,
                        decisions_per_round: payload.decisions_per_round,
                        seat: payload.seat,
                        partner: payload.partner,
                    };
                    this.reveals = {};
                    this.lastReveal = null;
                    this.roundSummary = null;
                    this.decision = null;
                    this.nudge = null;
                    break;
                case 'decision.opened':
                    this.decision = {
                        round: payload.round,
                        index: payload.decision,
                        opened_at: payload.opened_at,
                        deadline_at: payload.deadline_at,
                        choose_ms: payload.choose_ms,
                        your_choice: null,
                        chosen: false,
                        rejected: null,
                    };
                    this.nudge = null;
                    break;
                case 'decision.revealed':
                    this.publicReveal = payload;
                    break;
                case 'you.revealed':
                    this.lastReveal = payload;
                    this.reveals = { ...this.reveals, [payload.decision]: payload };
                    this.totalPoints = payload.total_points;
                    if (this.decision && this.decision.index === payload.decision) {
                        this.decision.your_choice = payload.you.choice;
                        this.decision.chosen = true;
                    }
                    break;
                case 'you.nudged':
                    this.nudge = payload.consecutive_timeouts;
                    break;
                case 'round.summary':
                    this.publicSummary = payload;
                    break;
                case 'you.round_summary':
                    this.roundSummary = payload;
                    this.totalPoints = payload.total_points;
                    break;
                case 'analysis.started':
                    this.beat = null;
                    break;
                case 'analysis.beat':
                    this.beat = payload;
                    break;
                case 'you.card':
                    this.card = payload;
                    this.cards = [...this.cards, payload];
                    break;
                case 'you.admitted':
                    this.isAdmitted = true;
                    this.subscribe(`session.${this.code}`);
                    break;
                case 'you.kicked':
                    this.kicked = true;
                    break;
                case 'session.ended':
                    this.ended = payload.reason;
                    break;
                default:
                    break;
            }
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

/** Turn an axios failure from POST join into { reason, message } a phone can show. */
export function normalizeJoinError(error) {
    const status = error?.response?.status;
    const body = error?.response?.data ?? {};
    if (status === 404) return { reason: 'not_found', message: 'No game with that code. Check the screen and try again.' };
    if (status === 422 && body?.errors?.username?.[0] === 'username taken') {
        return { reason: 'username_taken', message: 'Someone here already has that name. Pick another.' };
    }
    if (status === 422) return { reason: 'invalid', message: Object.values(body.errors ?? {}).flat()[0] ?? 'Check the code and your name.' };
    if (status === 409 && body.reason === 'session_full') return { reason: 'session_full', message: 'The room is full.' };
    if (status === 409 && body.reason === 'session_finished') return { reason: 'session_finished', message: 'That game is over.' };
    if (status === 429) return { reason: 'rate_limited', message: 'Too many tries. Wait a moment.' };
    if (!status) return { reason: 'network', message: "Can't reach the game. Check your connection." };
    return { reason: 'unknown', message: body.message ?? `Something went wrong (${status}).` };
}
