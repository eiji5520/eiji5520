// Sudoku Types
export type CellValue = number | null;

export interface Cell {
  value: CellValue;
  isInitial: boolean;
  isError: boolean;
}

export type Grid = Cell[][];

export interface Puzzle {
  id: number;
  difficulty: string;
  grid: number[][];
  solution: number[][];
}

export interface PuzzleData {
  puzzles: Puzzle[];
}

// Game State
export interface GameState {
  grid: Grid;
  solution: number[][];
  selectedCell: { row: number; col: number } | null;
  puzzleIndex: number;
  isCompleted: boolean;
}

// Purchase State
export interface PurchaseState {
  adsRemoved: boolean;
  isLoading: boolean;
  error: string | null;
}

// Storage Keys
export const STORAGE_KEYS = {
  GAME_STATE: 'sudoku_game_state',
  ADS_REMOVED: 'sudoku_ads_removed',
} as const;
