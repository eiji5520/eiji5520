import type { Difficulty } from '../types';
import { DIFFICULTY_LABELS } from '../types';
import './Controls.css';

interface ControlsProps {
  onInput: (value: number | null) => void;
  onReset: () => void;
  onNewPuzzle: () => void;
  onChangeDifficulty: (difficulty: Difficulty) => void;
  difficulty: Difficulty;
  isGenerating?: boolean;
}

const DIFFICULTIES: Difficulty[] = ['easy', 'normal', 'hard'];

export function Controls({
  onInput,
  onReset,
  onNewPuzzle,
  onChangeDifficulty,
  difficulty,
  isGenerating = false,
}: ControlsProps) {
  const numbers = [1, 2, 3, 4, 5, 6, 7, 8, 9];

  return (
    <div className="controls">
      {/* 難易度選択 */}
      <div className="difficulty-selector">
        <span className="difficulty-label">Difficulty:</span>
        <div className="difficulty-buttons">
          {DIFFICULTIES.map((d) => (
            <button
              key={d}
              className={`difficulty-button ${d === difficulty ? 'active' : ''}`}
              onClick={() => onChangeDifficulty(d)}
              disabled={isGenerating}
              aria-label={`Set difficulty to ${DIFFICULTY_LABELS[d]}`}
            >
              {DIFFICULTY_LABELS[d]}
            </button>
          ))}
        </div>
      </div>

      {/* 数字パッド */}
      <div className="number-pad">
        {numbers.map((num) => (
          <button
            key={num}
            className="number-button"
            onClick={() => onInput(num)}
            disabled={isGenerating}
            aria-label={`Input ${num}`}
          >
            {num}
          </button>
        ))}
      </div>

      {/* アクションボタン */}
      <div className="action-buttons">
        <button
          className="action-button delete-button"
          onClick={() => onInput(null)}
          disabled={isGenerating}
          aria-label="Delete"
        >
          Delete
        </button>
        <button
          className="action-button reset-button"
          onClick={onReset}
          disabled={isGenerating}
          aria-label="Reset puzzle"
        >
          Reset
        </button>
        <button
          className="action-button new-button"
          onClick={onNewPuzzle}
          disabled={isGenerating}
          aria-label="New puzzle"
        >
          {isGenerating ? 'Generating...' : 'New Puzzle'}
        </button>
      </div>
    </div>
  );
}
