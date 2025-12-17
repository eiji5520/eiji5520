import type { Cell as CellType } from '../types';
import './Cell.css';

interface CellProps {
  cell: CellType;
  row: number;
  col: number;
  isSelected: boolean;
  onSelect: (row: number, col: number) => void;
}

export function Cell({ cell, row, col, isSelected, onSelect }: CellProps) {
  const classNames = [
    'cell',
    cell.isInitial ? 'cell-initial' : '',
    cell.isError ? 'cell-error' : '',
    isSelected ? 'cell-selected' : '',
    // 3x3ボックスの境界線
    col % 3 === 2 && col !== 8 ? 'cell-border-right' : '',
    row % 3 === 2 && row !== 8 ? 'cell-border-bottom' : '',
  ]
    .filter(Boolean)
    .join(' ');

  return (
    <div
      className={classNames}
      onClick={() => onSelect(row, col)}
      role="button"
      tabIndex={0}
      aria-label={`Row ${row + 1}, Column ${col + 1}, ${cell.value || 'empty'}`}
    >
      {cell.value}
    </div>
  );
}
