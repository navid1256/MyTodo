import assert from 'node:assert/strict';
import {
    resolveMonthlyDay
} from '../public/assets/js/modules/repeat/repeat-rule.js';

assert.equal(
    resolveMonthlyDay(2028, 1, 31, false),
    29,
    'The 31st must fall back to February 29 in a leap year.'
);

assert.equal(
    resolveMonthlyDay(2027, 1, 31, false),
    28,
    'The 31st must fall back to February 28 in a non-leap year.'
);

assert.equal(
    resolveMonthlyDay(2026, 3, 31, false),
    30,
    'The 31st must fall back to April 30.'
);

assert.equal(
    resolveMonthlyDay(2027, 1, 15, true),
    28,
    'Last day of month must resolve to the actual final day.'
);

assert.equal(
    resolveMonthlyDay(2026, 6, 15, false),
    15,
    'A valid selected day must remain unchanged.'
);

console.log('repeat-rule tests passed');
