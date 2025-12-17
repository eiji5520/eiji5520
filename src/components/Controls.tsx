import './Controls.css';

interface ControlsProps {
  onInput: (value: number | null) => void;
  onReset: () => void;
  onNextPuzzle: () => void;
  puzzleIndex: number;
  puzzleCount: number;
}

export function Controls({
  onInput,
  onReset,
  onNextPuzzle,
  puzzleIndex,
  puzzleCount,
}: ControlsProps) {
  const numbers = [1, 2, 3, 4, 5, 6, 7, 8, 9];

  return (
    <div className="controls">
      <div className="number-pad">
        {numbers.map((num) => (
          <button
            key={num}
            className="number-button"
            onClick={() => onInput(num)}
            aria-label={`Input ${num}`}
          >
            {num}
          </button>
        ))}
      </div>

      <div className="action-buttons">
        <button
          className="action-button delete-button"
          onClick={() => onInput(null)}
          aria-label="Delete"
        >
          Delete
        </button>
        <button
          className="action-button reset-button"
          onClick={onReset}
          aria-label="Reset puzzle"
        >
          Reset
        </button>
        <button
          className="action-button next-button"
          onClick={onNextPuzzle}
          aria-label="Next puzzle"
        >
          Next ({puzzleIndex + 1}/{puzzleCount})
        </button>
      </div>
    </div>
  );
}
