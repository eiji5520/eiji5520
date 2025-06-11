package com.example.sudokuapp.util

import kotlin.random.Random

/** Utility class containing functions to generate and solve Sudoku puzzles using backtracking. */
object SudokuUtils {
    private const val BOARD_SIZE = 9

    /** Generate a solvable Sudoku puzzle with given number of cells removed based on difficulty. */
    fun generatePuzzle(removedCells: Int): Array<IntArray> {
        val board = Array(BOARD_SIZE) { IntArray(BOARD_SIZE) }
        fillDiagonalSubgrids(board)
        solveBoard(board)
        removeCells(board, removedCells)
        return board
    }

    /** Fill the diagonal 3x3 blocks, which are independent of each other. */
    private fun fillDiagonalSubgrids(board: Array<IntArray>) {
        for (i in 0 until BOARD_SIZE step 3) {
            fillSubgrid(board, i, i)
        }
    }

    private fun fillSubgrid(board: Array<IntArray>, row: Int, col: Int) {
        var num: Int
        for (i in 0 until 3) {
            for (j in 0 until 3) {
                do {
                    num = Random.nextInt(1, 10)
                } while (!isSafe(board, row + i, col + j, num))
                board[row + i][col + j] = num
            }
        }
    }

    /** Backtracking solver. Returns true if solved. */
    fun solveBoard(board: Array<IntArray>): Boolean {
        for (row in 0 until BOARD_SIZE) {
            for (col in 0 until BOARD_SIZE) {
                if (board[row][col] == 0) {
                    for (num in 1..9) {
                        if (isSafe(board, row, col, num)) {
                            board[row][col] = num
                            if (solveBoard(board)) {
                                return true
                            }
                            board[row][col] = 0
                        }
                    }
                    return false
                }
            }
        }
        return true
    }

    private fun isSafe(board: Array<IntArray>, row: Int, col: Int, num: Int): Boolean {
        return isRowSafe(board, row, num) &&
            isColSafe(board, col, num) &&
            isSubgridSafe(board, row - row % 3, col - col % 3, num)
    }

    private fun isRowSafe(board: Array<IntArray>, row: Int, num: Int): Boolean {
        for (col in 0 until BOARD_SIZE) {
            if (board[row][col] == num) return false
        }
        return true
    }

    private fun isColSafe(board: Array<IntArray>, col: Int, num: Int): Boolean {
        for (row in 0 until BOARD_SIZE) {
            if (board[row][col] == num) return false
        }
        return true
    }

    private fun isSubgridSafe(board: Array<IntArray>, startRow: Int, startCol: Int, num: Int): Boolean {
        for (r in 0 until 3) {
            for (c in 0 until 3) {
                if (board[startRow + r][startCol + c] == num) return false
            }
        }
        return true
    }

    /** Randomly remove cells to create a puzzle. */
    private fun removeCells(board: Array<IntArray>, removedCells: Int) {
        var count = removedCells
        while (count > 0) {
            val cellId = Random.nextInt(0, BOARD_SIZE * BOARD_SIZE)
            val row = cellId / BOARD_SIZE
            val col = cellId % BOARD_SIZE
            if (board[row][col] != 0) {
                board[row][col] = 0
                count--
            }
        }
    }
}
