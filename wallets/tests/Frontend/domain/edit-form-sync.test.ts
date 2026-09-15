import { describe, expect, it } from 'vitest';
import { diffEditFormIds } from '@/domain/reporting/edit-form-sync';

describe('diffEditFormIds', () => {
    it('adds ids present only in next list', () => {
        expect(diffEditFormIds([], [3, 5])).toEqual({ toAdd: [3, 5], toRemove: [] });
    });

    it('removes ids missing from next list', () => {
        expect(diffEditFormIds([1, 2], [2])).toEqual({ toAdd: [], toRemove: [1] });
    });

    it('handles mixed add and remove', () => {
        expect(diffEditFormIds([1, 2], [2, 9])).toEqual({ toAdd: [9], toRemove: [1] });
    });
});
