import assert from 'node:assert/strict';
import test from 'node:test';
import { createRange, edgeAt, moveRange, pixelsToMinutes, resizeRange, snap } from './geometry.js';
import { formatClock, formatTime } from '../utils/format.js';

test('snaps to five minutes', () => {
    assert.equal(snap(7), 5);
    assert.equal(snap(8), 10);
    assert.equal(snap(62), 60);
});

test('converts pixels to minutes', () => {
    assert.equal(pixelsToMinutes(56, 56), 60);
    assert.equal(pixelsToMinutes(28, 56), 30);
});

test('moves a range and keeps its duration', () => {
    assert.deepEqual(moveRange(600, 660, 32), { start: 630, end: 690 });
});

test('moving cannot leave the day', () => {
    assert.deepEqual(moveRange(60, 120, -500), { start: 0, end: 60 });
    assert.deepEqual(moveRange(1300, 1380, 500), { start: 1360, end: 1440 });
});

test('resizing the end changes only the end', () => {
    assert.deepEqual(resizeRange('end', 600, 660, 30), { start: 600, end: 690 });
});

test('resizing the start changes only the start', () => {
    assert.deepEqual(resizeRange('start', 600, 660, -30), { start: 570, end: 660 });
});

test('resizing keeps a minimum duration', () => {
    assert.deepEqual(resizeRange('end', 600, 660, -500), { start: 600, end: 605 });
    assert.deepEqual(resizeRange('start', 600, 660, 500), { start: 655, end: 660 });
});

test('resizing cannot pass midnight', () => {
    assert.equal(resizeRange('end', 1300, 1380, 500).end, 1440);
});

test('creates a range in both drag directions', () => {
    assert.deepEqual(createRange(540, 630), { start: 540, end: 630 });
    assert.deepEqual(createRange(630, 540), { start: 540, end: 630 });
});

test('a created range has a minimum length', () => {
    assert.deepEqual(createRange(540, 541), { start: 540, end: 555 });
});

test('detects the grab zone of a block', () => {
    assert.equal(edgeAt(2, 100), 'start');
    assert.equal(edgeAt(98, 100), 'end');
    assert.equal(edgeAt(50, 100), 'move');
    assert.equal(edgeAt(2, 18), 'move');
});

test('formats the clock with seconds', () => {
    assert.equal(formatClock(0), '0:00:00');
    assert.equal(formatClock(75), '0:01:15');
    assert.equal(formatClock(3725), '1:02:05');
    assert.equal(formatClock(-5), '0:00:00');
});

test('formats minutes since midnight as a time', () => {
    assert.equal(formatTime(0), '00:00');
    assert.equal(formatTime(1260), '21:00');
    assert.equal(formatTime(1440), '24:00');
});
