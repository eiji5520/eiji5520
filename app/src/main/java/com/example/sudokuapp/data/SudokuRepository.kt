package com.example.sudokuapp.data

import android.content.SharedPreferences
import com.example.sudokuapp.model.Difficulty
import com.example.sudokuapp.util.SudokuUtils
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

/** Repository responsible for generating puzzles and storing history. */
class SudokuRepository(private val prefs: SharedPreferences) {

    suspend fun generateNewPuzzle(difficulty: Difficulty): Array<IntArray> = withContext(Dispatchers.Default) {
        SudokuUtils.generatePuzzle(difficulty.removed)
    }

    suspend fun solvePuzzle(board: Array<IntArray>): Array<IntArray> = withContext(Dispatchers.Default) {
        val copy = Array(board.size) { board[it].clone() }
        SudokuUtils.solveBoard(copy)
        copy
    }

    fun saveClearTime(timeMillis: Long) {
        val history = prefs.getStringSet(KEY_HISTORY, mutableSetOf())?.toMutableSet() ?: mutableSetOf()
        history.add(timeMillis.toString())
        prefs.edit().putStringSet(KEY_HISTORY, history).apply()
    }

    fun getClearHistory(): List<Long> {
        return prefs.getStringSet(KEY_HISTORY, mutableSetOf())
            ?.mapNotNull { it.toLongOrNull() }
            ?.sortedDescending() ?: emptyList()
    }

    companion object {
        private const val KEY_HISTORY = "history"
    }
}
