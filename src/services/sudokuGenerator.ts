/**
 * 数独パズル自動生成サービス
 * - バックトラッキング法による完全盤面生成
 * - 解の一意性保証
 * - 難易度に応じた穴あけ処理
 */

export type Difficulty = 'easy' | 'normal' | 'hard';

// 難易度ごとの空欄数設定
const BLANK_COUNTS: Record<Difficulty, { min: number; max: number }> = {
  easy: { min: 35, max: 40 },
  normal: { min: 41, max: 50 },
  hard: { min: 51, max: 58 },
};

/**
 * 配列をシャッフル（Fisher-Yates）
 */
function shuffle<T>(array: T[]): T[] {
  const result = [...array];
  for (let i = result.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [result[i], result[j]] = [result[j], result[i]];
  }
  return result;
}

/**
 * 指定位置に数字を置けるかチェック
 */
function isValidPlacement(
  board: number[][],
  row: number,
  col: number,
  num: number
): boolean {
  // 行チェック
  for (let c = 0; c < 9; c++) {
    if (board[row][c] === num) return false;
  }

  // 列チェック
  for (let r = 0; r < 9; r++) {
    if (board[r][col] === num) return false;
  }

  // 3x3ボックスチェック
  const boxRow = Math.floor(row / 3) * 3;
  const boxCol = Math.floor(col / 3) * 3;
  for (let r = boxRow; r < boxRow + 3; r++) {
    for (let c = boxCol; c < boxCol + 3; c++) {
      if (board[r][c] === num) return false;
    }
  }

  return true;
}

/**
 * 空のセル位置を探す
 */
function findEmptyCell(board: number[][]): { row: number; col: number } | null {
  for (let row = 0; row < 9; row++) {
    for (let col = 0; col < 9; col++) {
      if (board[row][col] === 0) {
        return { row, col };
      }
    }
  }
  return null;
}

/**
 * バックトラッキングで盤面を解く（ヒント機能などに利用可能）
 * @returns 解けたらtrue
 */
export function solveSudoku(board: number[][]): boolean {
  const empty = findEmptyCell(board);
  if (!empty) return true; // 全て埋まった

  const { row, col } = empty;

  for (let num = 1; num <= 9; num++) {
    if (isValidPlacement(board, row, col, num)) {
      board[row][col] = num;
      if (solveSudoku(board)) return true;
      board[row][col] = 0;
    }
  }

  return false;
}

/**
 * 解の数をカウント（最大2まで）
 * 2つ以上見つかったら早期リターン
 */
function countSolutions(board: number[][], count = { value: 0 }): number {
  if (count.value >= 2) return count.value;

  const empty = findEmptyCell(board);
  if (!empty) {
    count.value++;
    return count.value;
  }

  const { row, col } = empty;

  for (let num = 1; num <= 9; num++) {
    if (isValidPlacement(board, row, col, num)) {
      board[row][col] = num;
      countSolutions(board, count);
      board[row][col] = 0;

      if (count.value >= 2) return count.value;
    }
  }

  return count.value;
}

/**
 * 解が一意かどうかチェック
 */
function hasUniqueSolution(board: number[][]): boolean {
  const boardCopy = board.map(row => [...row]);
  return countSolutions(boardCopy) === 1;
}

/**
 * 完成した9x9数独盤面を生成
 */
function generateFullBoard(): number[][] {
  const board: number[][] = Array(9)
    .fill(null)
    .map(() => Array(9).fill(0));

  // バックトラッキングでランダムに埋める
  function fillBoard(board: number[][]): boolean {
    const empty = findEmptyCell(board);
    if (!empty) return true;

    const { row, col } = empty;
    const numbers = shuffle([1, 2, 3, 4, 5, 6, 7, 8, 9]);

    for (const num of numbers) {
      if (isValidPlacement(board, row, col, num)) {
        board[row][col] = num;
        if (fillBoard(board)) return true;
        board[row][col] = 0;
      }
    }

    return false;
  }

  fillBoard(board);
  return board;
}

/**
 * 難易度に応じてセルを空欄にする
 * 解が一意であることを保証
 */
function removeNumbersByDifficulty(
  solution: number[][],
  difficulty: Difficulty
): number[][] {
  const { min, max } = BLANK_COUNTS[difficulty];
  const targetBlanks = Math.floor(Math.random() * (max - min + 1)) + min;

  const board = solution.map(row => [...row]);

  // 全セル位置をシャッフル
  const positions: { row: number; col: number }[] = [];
  for (let row = 0; row < 9; row++) {
    for (let col = 0; col < 9; col++) {
      positions.push({ row, col });
    }
  }
  const shuffledPositions = shuffle(positions);

  let blanksCreated = 0;

  for (const { row, col } of shuffledPositions) {
    if (blanksCreated >= targetBlanks) break;

    const backup = board[row][col];
    if (backup === 0) continue;

    board[row][col] = 0;

    // 解の一意性チェック
    if (hasUniqueSolution(board)) {
      blanksCreated++;
    } else {
      // 一意でなければ戻す
      board[row][col] = backup;
    }
  }

  return board;
}

export interface GeneratedPuzzle {
  grid: number[][];
  solution: number[][];
  difficulty: Difficulty;
  blankCount: number;
}

/**
 * 新しい数独パズルを生成
 */
export function generatePuzzle(difficulty: Difficulty): GeneratedPuzzle {
  const solution = generateFullBoard();
  const grid = removeNumbersByDifficulty(solution, difficulty);

  // 空欄数をカウント
  let blankCount = 0;
  for (let row = 0; row < 9; row++) {
    for (let col = 0; col < 9; col++) {
      if (grid[row][col] === 0) blankCount++;
    }
  }

  return {
    grid,
    solution,
    difficulty,
    blankCount,
  };
}

/**
 * 盤面が正しく解かれているかチェック
 */
export function isBoardComplete(board: number[][], solution: number[][]): boolean {
  for (let row = 0; row < 9; row++) {
    for (let col = 0; col < 9; col++) {
      if (board[row][col] !== solution[row][col]) {
        return false;
      }
    }
  }
  return true;
}

/**
 * 指定セルが正解かどうかチェック
 */
export function isCellCorrect(
  value: number | null,
  row: number,
  col: number,
  solution: number[][]
): boolean {
  if (value === null) return true; // 空欄はエラーではない
  return value === solution[row][col];
}
