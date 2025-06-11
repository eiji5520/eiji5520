package com.example.sudokuapp.ui

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.sudokuapp.model.Difficulty
import com.example.sudokuapp.viewmodel.SudokuViewModel

/** Composable displaying the entire Sudoku game UI. */
@Composable
fun SudokuScreen(viewModel: SudokuViewModel) {
    val board by viewModel.boardState.collectAsState()
    var selectedCell by remember { mutableStateOf<Pair<Int, Int>?>(null) }

    Column(modifier = Modifier.fillMaxSize().padding(16.dp), horizontalAlignment = Alignment.CenterHorizontally) {
        DifficultySelector { viewModel.newGame(it) }
        Spacer(Modifier.height(16.dp))
        Board(board, selectedCell) { r, c -> selectedCell = Pair(r, c) }
        Spacer(Modifier.height(16.dp))
        NumberPad { number ->
            selectedCell?.let { (r, c) -> viewModel.setCellValue(r, c, number) }
        }
        Spacer(Modifier.height(16.dp))
        Row {
            Button(onClick = { viewModel.solveGame() }) { Text("Solve") }
        }
        Spacer(Modifier.height(16.dp))
        HistoryList(viewModel.history)
    }
}

@Composable
fun DifficultySelector(onSelect: (Difficulty) -> Unit) {
    Row(horizontalArrangement = Arrangement.SpaceEvenly) {
        Difficulty.values().forEach { diff ->
            Button(onClick = { onSelect(diff) }, modifier = Modifier.padding(4.dp)) {
                Text(diff.name)
            }
        }
    }
}

@Composable
fun Board(board: Array<IntArray>, selected: Pair<Int, Int>?, onCellSelected: (Int, Int) -> Unit) {
    Column {
        board.forEachIndexed { rowIdx, row ->
            Row {
                row.forEachIndexed { colIdx, value ->
                    val isSelected = selected?.first == rowIdx && selected.second == colIdx
                    Box(
                        modifier = Modifier
                            .size(36.dp)
                            .border(BorderStroke(1.dp, Color.Black))
                            .background(if (isSelected) Color.LightGray else Color.White)
                            .clickable { onCellSelected(rowIdx, colIdx) },
                        contentAlignment = Alignment.Center
                    ) {
                        if (value != 0) Text(value.toString(), fontSize = 18.sp)
                    }
                }
            }
        }
    }
}

@Composable
fun NumberPad(onNumberSelected: (Int) -> Unit) {
    Column {
        for (i in 1..9 step 3) {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.Center) {
                for (j in i until i + 3) {
                    OutlinedButton(
                        modifier = Modifier.padding(4.dp).size(48.dp),
                        onClick = { onNumberSelected(j) }
                    ) { Text(j.toString()) }
                }
            }
        }
    }
}

@Composable
fun HistoryList(times: List<Long>) {
    Column(modifier = Modifier.fillMaxWidth()) {
        Text("History", style = MaterialTheme.typography.titleMedium)
        times.forEach { time ->
            Text("${time}ms")
        }
    }
}
