package com.example.sudokuapp.viewmodel

import android.app.Application
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.example.sudokuapp.data.SudokuRepository
import com.example.sudokuapp.model.Difficulty
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch

/** Holds UI state and exposes operations for the Sudoku screen. */
class SudokuViewModel(app: Application) : AndroidViewModel(app) {

    private val repository = SudokuRepository(
        app.getSharedPreferences("sudoku_prefs", Application.MODE_PRIVATE)
    )

    private val _boardState = MutableStateFlow(Array(9) { IntArray(9) })
    val boardState: StateFlow<Array<IntArray>> = _boardState

    val history: List<Long>
        get() = repository.getClearHistory()

    fun newGame(difficulty: Difficulty) {
        viewModelScope.launch {
            _boardState.value = repository.generateNewPuzzle(difficulty)
        }
    }

    fun solveGame() {
        viewModelScope.launch {
            _boardState.value = repository.solvePuzzle(_boardState.value)
        }
    }

    fun setCellValue(row: Int, col: Int, value: Int) {
        val board = _boardState.value.map { it.clone() }.toTypedArray()
        board[row][col] = value
        _boardState.value = board
    }

    fun saveClearTime(timeMillis: Long) {
        repository.saveClearTime(timeMillis)
    }
}
