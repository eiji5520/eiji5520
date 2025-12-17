// Sudoku Types
export type CellValue = number | null;

export type Difficulty = 'easy' | 'normal' | 'hard';

export interface Cell {
  value: CellValue;
  isInitial: boolean;
  isError: boolean;
}

export type Grid = Cell[][];

// Legacy: JSON puzzle format (kept for backwards compatibility)
export interface Puzzle {
  id: number;
  difficulty: string;
  grid: number[][];
  solution: number[][];
}

export interface PuzzleData {
  puzzles: Puzzle[];
}

// Game State (updated for auto-generation)
export interface GameState {
  grid: Grid;
  initialGrid: number[][];  // ヒント固定用（生成時の穴あき盤面）
  solution: number[][];
  selectedCell: { row: number; col: number } | null;
  difficulty: Difficulty;
  isCompleted: boolean;
  // Legacy field - kept for migration
  puzzleIndex?: number;
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

// Difficulty labels for UI
export const DIFFICULTY_LABELS: Record<Difficulty, string> = {
  easy: 'Easy',
  normal: 'Normal',
  hard: 'Hard',
};
