import { useState, useEffect, useCallback } from 'react';
import type { Grid, Cell, Puzzle, PuzzleData, GameState } from '../types';
import { STORAGE_KEYS } from '../types';

/**
 * パズルデータからGridを生成
 */
function createGrid(puzzle: Puzzle): Grid {
  return puzzle.grid.map((row) =>
    row.map((value): Cell => ({
      value: value === 0 ? null : value,
      isInitial: value !== 0,
      isError: false,
    }))
  );
}

/**
 * グリッドのエラー状態を更新
 */
function updateGridErrors(grid: Grid, solution: number[][]): Grid {
  return grid.map((row, rowIndex) =>
    row.map((cell, colIndex): Cell => {
      if (cell.value === null || cell.isInitial) {
        return { ...cell, isError: false };
      }
      const isError = cell.value !== solution[rowIndex][colIndex];
      return { ...cell, isError };
    })
  );
}

/**
 * ゲームが完了したかチェック
 */
function checkCompletion(grid: Grid, solution: number[][]): boolean {
  for (let row = 0; row < 9; row++) {
    for (let col = 0; col < 9; col++) {
      const cell = grid[row][col];
      if (cell.value === null || cell.value !== solution[row][col]) {
        return false;
      }
    }
  }
  return true;
}

export function useSudoku() {
  const [puzzles, setPuzzles] = useState<Puzzle[]>([]);
  const [grid, setGrid] = useState<Grid>([]);
  const [solution, setSolution] = useState<number[][]>([]);
  const [selectedCell, setSelectedCell] = useState<{ row: number; col: number } | null>(null);
  const [puzzleIndex, setPuzzleIndex] = useState(0);
  const [isCompleted, setIsCompleted] = useState(false);
  const [isLoading, setIsLoading] = useState(true);

  /**
   * localStorageから状態を読み込む
   */
  const loadFromStorage = useCallback((): GameState | null => {
    try {
      const stored = localStorage.getItem(STORAGE_KEYS.GAME_STATE);
      if (stored) {
        return JSON.parse(stored);
      }
    } catch (error) {
      console.error('Failed to load game state:', error);
    }
    return null;
  }, []);

  /**
   * localStorageに状態を保存
   */
  const saveToStorage = useCallback((state: Partial<GameState>) => {
    try {
      const currentState = loadFromStorage() || {};
      const newState = { ...currentState, ...state };
      localStorage.setItem(STORAGE_KEYS.GAME_STATE, JSON.stringify(newState));
    } catch (error) {
      console.error('Failed to save game state:', error);
    }
  }, [loadFromStorage]);

  /**
   * パズルデータを読み込む
   */
  useEffect(() => {
    async function loadPuzzles() {
      try {
        const response = await fetch('/puzzles/easy.json');
        const data: PuzzleData = await response.json();
        setPuzzles(data.puzzles);

        // localStorageから状態を復元
        const savedState = loadFromStorage();

        if (savedState && savedState.grid && savedState.grid.length > 0) {
          // 保存された状態を復元
          const idx = savedState.puzzleIndex || 0;
          setPuzzleIndex(idx);
          setGrid(savedState.grid);
          setSolution(savedState.solution || data.puzzles[idx].solution);
          setIsCompleted(savedState.isCompleted || false);
        } else {
          // 新規ゲーム開始
          const puzzle = data.puzzles[0];
          setGrid(createGrid(puzzle));
          setSolution(puzzle.solution);
        }

        setIsLoading(false);
      } catch (error) {
        console.error('Failed to load puzzles:', error);
        setIsLoading(false);
      }
    }

    loadPuzzles();
  }, [loadFromStorage]);

  /**
   * 状態が変わったら保存
   */
  useEffect(() => {
    if (!isLoading && grid.length > 0) {
      saveToStorage({
        grid,
        solution,
        selectedCell,
        puzzleIndex,
        isCompleted,
      });
    }
  }, [grid, solution, selectedCell, puzzleIndex, isCompleted, isLoading, saveToStorage]);

  /**
   * セルを選択
   */
  const selectCell = useCallback((row: number, col: number) => {
    setSelectedCell({ row, col });
  }, []);

  /**
   * セルに値を入力
   */
  const inputValue = useCallback((value: number | null) => {
    if (!selectedCell) return;

    const { row, col } = selectedCell;
    const cell = grid[row][col];

    // 初期セルは編集不可
    if (cell.isInitial) return;

    setGrid(prevGrid => {
      const newGrid = prevGrid.map(r => r.map(c => ({ ...c })));
      newGrid[row][col] = {
        ...newGrid[row][col],
        value,
        isError: false,
      };

      // エラーチェック
      const gridWithErrors = updateGridErrors(newGrid, solution);

      // 完了チェック
      const completed = checkCompletion(gridWithErrors, solution);
      if (completed !== isCompleted) {
        setIsCompleted(completed);
      }

      return gridWithErrors;
    });
  }, [selectedCell, grid, solution, isCompleted]);

  /**
   * ゲームをリセット（現在のパズルをやり直し）
   */
  const resetGame = useCallback(() => {
    if (puzzles.length === 0) return;

    const puzzle = puzzles[puzzleIndex];
    setGrid(createGrid(puzzle));
    setSelectedCell(null);
    setIsCompleted(false);
  }, [puzzles, puzzleIndex]);

  /**
   * 次のパズルに切り替え
   */
  const nextPuzzle = useCallback(() => {
    if (puzzles.length === 0) return;

    const nextIndex = (puzzleIndex + 1) % puzzles.length;
    const puzzle = puzzles[nextIndex];

    setPuzzleIndex(nextIndex);
    setGrid(createGrid(puzzle));
    setSolution(puzzle.solution);
    setSelectedCell(null);
    setIsCompleted(false);
  }, [puzzles, puzzleIndex]);

  /**
   * 特定のパズルに切り替え
   */
  const selectPuzzle = useCallback((index: number) => {
    if (index < 0 || index >= puzzles.length) return;

    const puzzle = puzzles[index];
    setPuzzleIndex(index);
    setGrid(createGrid(puzzle));
    setSolution(puzzle.solution);
    setSelectedCell(null);
    setIsCompleted(false);
  }, [puzzles]);

  return {
    grid,
    selectedCell,
    puzzleIndex,
    puzzleCount: puzzles.length,
    isCompleted,
    isLoading,
    selectCell,
    inputValue,
    resetGame,
    nextPuzzle,
    selectPuzzle,
  };
}
