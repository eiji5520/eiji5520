import type { Grid } from '../types';
import { Cell } from './Cell';
import './SudokuBoard.css';

interface SudokuBoardProps {
  grid: Grid;
  selectedCell: { row: number; col: number } | null;
  onSelectCell: (row: number, col: number) => void;
}

export function SudokuBoard({ grid, selectedCell, onSelectCell }: SudokuBoardProps) {
  if (grid.length === 0) {
    return <div className="sudoku-board-loading">Loading...</div>;
  }

  return (
    <div className="sudoku-board">
      {grid.map((row, rowIndex) =>
        row.map((cell, colIndex) => (
          <Cell
            key={`${rowIndex}-${colIndex}`}
            cell={cell}
            row={rowIndex}
            col={colIndex}
            isSelected={
              selectedCell?.row === rowIndex && selectedCell?.col === colIndex
            }
            onSelect={onSelectCell}
          />
        ))
      )}
    </div>
  );
}
