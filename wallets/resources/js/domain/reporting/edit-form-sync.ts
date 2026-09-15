export type EditableItemId = number | string;

/**
 * Diff item ids so edit-form maps stay in sync after Inertia prop updates
 * (same page component is reused after create/update redirect).
 */
export function diffEditFormIds(
    existingIds: readonly EditableItemId[],
    nextIds: readonly EditableItemId[],
): { toAdd: EditableItemId[]; toRemove: EditableItemId[] } {
    const existing = new Set(existingIds.map(String));
    const next = new Set(nextIds.map(String));

    return {
        toAdd: nextIds.filter((id) => !existing.has(String(id))),
        toRemove: existingIds.filter((id) => !next.has(String(id))),
    };
}
