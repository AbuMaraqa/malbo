import type { GridCell, GridRow } from './types';

/** What one size run of a color holds now: one of each size that can be ordered. */
export interface SizeRun {
    /** The sizes a run takes. */
    sizes: string[];
    /** Sizes of this color that are sold out (or not sold wholesale): left out of the run. */
    leftOut: string[];
    /** The most runs the stock allows: the smallest available quantity among the run's sizes; null for no limit. */
    max: number | null;
}

function cellsOf(row: GridRow): GridCell[] {
    return row.cells.filter((cell): cell is GridCell => cell !== null);
}

export function sizeRun(row: GridRow): SizeRun {
    const cells = cellsOf(row);
    const orderable = cells.filter((cell) => cell.orderable);
    const limits = orderable.map((cell) => cell.available).filter((available): available is number => available !== null);

    return {
        sizes: orderable.map((cell) => cell.size ?? ''),
        leftOut: cells.filter((cell) => !cell.orderable).map((cell) => cell.size ?? ''),
        max: limits.length ? Math.min(...limits) : null,
    };
}

/**
 * The row's quantities for N runs: N of every size the run takes (N capped at what the stock allows,
 * so every size gets the same number), 0 of the sizes left out.
 *
 * @returns item id => pieces, and the N actually used
 */
export function runQuantities(row: GridRow, count: number): { quantities: Record<number, number>; runs: number } {
    const run = sizeRun(row);
    const runs = Math.max(0, run.max === null ? count : Math.min(count, run.max));
    const quantities: Record<number, number> = {};

    for (const cell of cellsOf(row)) {
        quantities[cell.id] = cell.orderable ? runs : 0;
    }

    return { quantities, runs };
}
