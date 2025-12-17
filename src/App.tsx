import { useState, useEffect, useCallback } from 'react';
import { SudokuBoard } from './components/SudokuBoard';
import { Controls } from './components/Controls';
import { Settings } from './components/Settings';
import { BannerAd } from './components/BannerAd';
import { useSudoku } from './hooks/useSudoku';
import { usePurchase } from './hooks/usePurchase';
import { DIFFICULTY_LABELS } from './types';
import './App.css';

function App() {
  const {
    grid,
    selectedCell,
    difficulty,
    isCompleted,
    isLoading,
    isGenerating,
    selectCell,
    inputValue,
    resetGame,
    newPuzzle,
    changeDifficulty,
  } = useSudoku();

  const { adsRemoved } = usePurchase();
  const [settingsOpen, setSettingsOpen] = useState(false);

  // キーボード入力のハンドリング
  const handleKeyDown = useCallback(
    (e: KeyboardEvent) => {
      if (settingsOpen || isGenerating) return;

      // 数字キー (1-9)
      if (e.key >= '1' && e.key <= '9') {
        inputValue(parseInt(e.key, 10));
        return;
      }

      // 削除キー
      if (e.key === 'Backspace' || e.key === 'Delete' || e.key === '0') {
        inputValue(null);
        return;
      }

      // 矢印キーでセル移動
      if (selectedCell && ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(e.key)) {
        e.preventDefault();
        let { row, col } = selectedCell;

        switch (e.key) {
          case 'ArrowUp':
            row = Math.max(0, row - 1);
            break;
          case 'ArrowDown':
            row = Math.min(8, row + 1);
            break;
          case 'ArrowLeft':
            col = Math.max(0, col - 1);
            break;
          case 'ArrowRight':
            col = Math.min(8, col + 1);
            break;
        }

        selectCell(row, col);
      }
    },
    [inputValue, selectCell, selectedCell, settingsOpen, isGenerating]
  );

  useEffect(() => {
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [handleKeyDown]);

  if (isLoading) {
    return (
      <div className="app">
        <div className="loading">
          {isGenerating ? 'Generating puzzle...' : 'Loading...'}
        </div>
      </div>
    );
  }

  return (
    <div className="app">
      <header className="app-header">
        <h1>Sudoku</h1>
        <span className="current-difficulty">{DIFFICULTY_LABELS[difficulty]}</span>
        <button
          className="settings-button"
          onClick={() => setSettingsOpen(true)}
          aria-label="Open settings"
        >
          Settings
        </button>
      </header>

      <main className="app-main">
        {isCompleted && (
          <div className="completion-message">
            Congratulations! Puzzle completed!
          </div>
        )}

        <SudokuBoard
          grid={grid}
          selectedCell={selectedCell}
          onSelectCell={selectCell}
        />

        <Controls
          onInput={inputValue}
          onReset={resetGame}
          onNewPuzzle={newPuzzle}
          onChangeDifficulty={changeDifficulty}
          difficulty={difficulty}
          isGenerating={isGenerating}
        />
      </main>

      <Settings isOpen={settingsOpen} onClose={() => setSettingsOpen(false)} />

      {/* 広告バナー (購入済みの場合は表示しない) */}
      <BannerAd adsRemoved={adsRemoved} />
    </div>
  );
}

export default App;
