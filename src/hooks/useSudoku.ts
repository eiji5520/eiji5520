import { useState, useEffect, useCallback, useRef } from 'react';
import type { Grid, Cell, GameState, Difficulty } from '../types';
import { STORAGE_KEYS } from '../types';
import { generatePuzzle } from '../services/sudokuGenerator';

/**
 * 数値配列からGridを生成
 */
function createGridFromNumbers(numbers: number[][]): Grid {
  return numbers.map((row) =>
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
  const [grid, setGrid] = useState<Grid>([]);
  const [initialGrid, setInitialGrid] = useState<number[][]>([]);
  const [solution, setSolution] = useState<number[][]>([]);
  const [selectedCell, setSelectedCell] = useState<{ row: number; col: number } | null>(null);
  const [difficulty, setDifficulty] = useState<Difficulty>('easy');
  const [isCompleted, setIsCompleted] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [isGenerating, setIsGenerating] = useState(false);

  // 初期化済みフラグ（二重初期化防止）
  const initialized = useRef(false);

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
  const saveToStorage = useCallback((state: GameState) => {
    try {
      localStorage.setItem(STORAGE_KEYS.GAME_STATE, JSON.stringify(state));
    } catch (error) {
      console.error('Failed to save game state:', error);
    }
  }, []);

  /**
   * 新しいゲームを開始（自動生成）
   */
  const startNewGame = useCallback((newDifficulty: Difficulty) => {
    setIsGenerating(true);

    // UIをブロックしないよう次フレームで実行
    setTimeout(() => {
      try {
        const puzzle = generatePuzzle(newDifficulty);

        setInitialGrid(puzzle.grid);
        setSolution(puzzle.solution);
        setGrid(createGridFromNumbers(puzzle.grid));
        setDifficulty(newDifficulty);
        setSelectedCell(null);
        setIsCompleted(false);

        console.log(`[Sudoku] Generated ${newDifficulty} puzzle with ${puzzle.blankCount} blanks`);
      } catch (error) {
        console.error('Failed to generate puzzle:', error);
      } finally {
        setIsGenerating(false);
        setIsLoading(false);
      }
    }, 10);
  }, []);

  /**
   * 初期化：localStorageから復元または新規生成
   */
  useEffect(() => {
    if (initialized.current) return;
    initialized.current = true;

    const savedState = loadFromStorage();

    if (
      savedState &&
      savedState.grid &&
      savedState.grid.length > 0 &&
      savedState.initialGrid &&
      savedState.solution
    ) {
      // 保存された状態を復元
      setGrid(savedState.grid);
      setInitialGrid(savedState.initialGrid);
      setSolution(savedState.solution);
      setDifficulty(savedState.difficulty || 'easy');
      setIsCompleted(savedState.isCompleted || false);
      setIsLoading(false);
      console.log('[Sudoku] Restored game from localStorage');
    } else {
      // 新規ゲーム開始
      startNewGame('easy');
    }
  }, [loadFromStorage, startNewGame]);

  /**
   * 状態が変わったら保存
   */
  useEffect(() => {
    if (!isLoading && !isGenerating && grid.length > 0 && initialGrid.length > 0) {
      saveToStorage({
        grid,
        initialGrid,
        solution,
        selectedCell,
        difficulty,
        isCompleted,
      });
    }
  }, [grid, initialGrid, solution, selectedCell, difficulty, isCompleted, isLoading, isGenerating, saveToStorage]);

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
    const cell = grid[row]?.[col];

    // 初期セルは編集不可
    if (!cell || cell.isInitial) return;

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
    if (initialGrid.length === 0) return;

    setGrid(createGridFromNumbers(initialGrid));
    setSelectedCell(null);
    setIsCompleted(false);
  }, [initialGrid]);

  /**
   * 新しいパズルを生成（現在の難易度で）
   */
  const newPuzzle = useCallback(() => {
    startNewGame(difficulty);
  }, [difficulty, startNewGame]);

  /**
   * 難易度を変更して新しいパズルを生成
   */
  const changeDifficulty = useCallback((newDifficulty: Difficulty) => {
    if (newDifficulty === difficulty && grid.length > 0) {
      // 同じ難易度で新規生成
      startNewGame(newDifficulty);
    } else {
      // 難易度変更
      startNewGame(newDifficulty);
    }
  }, [difficulty, grid.length, startNewGame]);

  return {
    grid,
    selectedCell,
    difficulty,
    isCompleted,
    isLoading: isLoading || isGenerating,
    isGenerating,
    selectCell,
    inputValue,
    resetGame,
    newPuzzle,
    changeDifficulty,
  };
}
