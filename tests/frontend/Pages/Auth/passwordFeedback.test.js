import { describe, expect, test } from 'vitest';
import { passwordsMatch } from '@/Pages/Auth/parts/passwordFeedback';

describe('passwordsMatch', () => {
    test('is true only when both fields are filled and equal', () => {
        expect(passwordsMatch('abcdefgh', 'abcdefgh')).toBe(true);
    });

    test('does not call two empty fields a match', () => {
        expect(passwordsMatch('', '')).toBe(false);
    });

    test('is false while either side is empty', () => {
        expect(passwordsMatch('', 'abcdefgh')).toBe(false);
        expect(passwordsMatch('abcdefgh', '')).toBe(false);
    });

    test('is false for a prefix of the password', () => {
        expect(passwordsMatch('supersecret', 'super')).toBe(false);
    });
});
