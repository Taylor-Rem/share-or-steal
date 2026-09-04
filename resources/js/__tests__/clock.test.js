import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createClock } from '../shared/clock';

describe('clock offset (CONTRACT.md § 6)', () => {
    beforeEach(() => vi.useFakeTimers({ now: new Date('2026-09-04T17:00:00.000Z') }));
    afterEach(() => vi.useRealTimers());

    it('takes the first sample outright', () => {
        const clock = createClock();
        expect(clock.offset()).toBeNull();
        clock.sync('2026-09-04T17:00:05.000Z');
        expect(clock.offset()).toBe(5000);
        expect(clock.now()).toBe(Date.parse('2026-09-04T17:00:05.000Z'));
    });

    it('nudges later samples by a fifth, never replacing wholesale', () => {
        const clock = createClock();
        clock.sync('2026-09-04T17:00:05.000Z');
        clock.sync('2026-09-04T17:00:10.000Z'); // sample 10 000, offset 5 000 -> 6 000
        expect(clock.offset()).toBe(6000);
    });

    it('counts down toward a deadline in server time and never goes negative', () => {
        const clock = createClock();
        clock.sync('2026-09-04T17:00:05.000Z');
        expect(clock.remaining('2026-09-04T17:00:10.000Z')).toBe(5000);
        vi.advanceTimersByTime(3000);
        expect(clock.remaining('2026-09-04T17:00:10.000Z')).toBe(2000);
        vi.advanceTimersByTime(3000);
        expect(clock.remaining('2026-09-04T17:00:10.000Z')).toBe(0);
        expect(clock.remaining(null)).toBe(0);
    });

    it('ignores garbage', () => {
        const clock = createClock();
        clock.sync(undefined);
        clock.sync('not a date');
        expect(clock.offset()).toBeNull();
    });
});
