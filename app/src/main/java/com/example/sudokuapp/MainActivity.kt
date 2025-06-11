package com.example.sudokuapp

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.viewModels
import com.example.sudokuapp.ui.SudokuScreen
import com.example.sudokuapp.viewmodel.SudokuViewModel

/** Main Activity hosting the Sudoku game screen. */
class MainActivity : ComponentActivity() {

    private val viewModel: SudokuViewModel by viewModels()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            SudokuScreen(viewModel)
        }
    }
}
