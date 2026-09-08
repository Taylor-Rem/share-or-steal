import { buildFixture } from './game';

/**
 * Feed a scripted game into the store on a timer. `?fixture=1` on the phone or the screen
 * turns it on; `?fixture=fast` uses the fast clocks; `&anonymous=1` and `&comparison=1`
 * pick the variants. Returns a controller with `stop()`.
 *
 * Choices made while a decision is open go to the fixture instead of the server, and the
 * next reveal reflects them; a second tap is rejected as `already_chosen`, like the API.
 */
export function useFixture(store, options = {}) {
    const fixture = buildFixture(options);
    const ctx = { choice: null, open: false, openedAt: 0, responseMs: null };
    let timer = null;
    let index = 0;
    let stopped = false;

    const controller = {
        choose(choice) {
            if (!ctx.open || !store.decision) return;
            if (ctx.choice !== null) {
                store.decision.rejected = 'already_chosen';
                store.decision.chosen = true;
                return;
            }
            ctx.choice = choice;
            ctx.responseMs = Date.now() - ctx.openedAt;
            store.decision.your_choice = choice;
            store.decision.chosen = true;
        },
        stop() {
            stopped = true;
            clearTimeout(timer);
            if (store._fixture === controller) store._fixture = null;
        },
        get done() {
            return index >= fixture.steps.length;
        },
    };

    store.configure({ code: options.code ?? 'DEMO', kind: options.kind ?? store.kind ?? 'phone' });
    store._fixture = controller;
    store.connection = 'fixture';
    store.applyMe(fixture.me);
    store.players = [...fixture.players];

    const next = () => {
        if (stopped || index >= fixture.steps.length) return;
        const step = fixture.steps[index++];
        timer = setTimeout(() => {
            if (stopped) return;
            for (const { channel, event, payload } of step.events(Date.now(), ctx)) store.receive(channel, event, payload);
            next();
        }, step.delay);
    };
    next();

    return controller;
}
