package com.example.sudokuapp.model

/** Represents a single cell on the Sudoku board. */
data class SudokuCell(
    val row: Int,
    val col: Int,
    var value: Int = 0,
    var isFixed: Boolean = false,
    var isValid: Boolean = true
)

/** Difficulty enumeration controlling the number of removed cells. */
enum class Difficulty(val removed: Int) {
    EASY(30),
    MEDIUM(40),
    HARD(50)
}
