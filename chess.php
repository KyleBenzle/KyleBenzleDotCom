<?php
// Store game state in a file
$game_state_file = 'chess_game_state.txt';
$score_file = 'chess_score.txt';

// Handle AJAX request for checking updates
if (isset($_GET['check_for_updates'])) {
    $game_state = getGameState($game_state_file);
    
    // Set headers to prevent caching - critical for mobile browsers
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Return the current game state with a timestamp to ensure uniqueness
    echo json_encode([
        'fen' => $game_state['fen'],
        'timestamp' => time(),
        'turn' => $game_state['currentTurn']
    ]);
    exit;
}

// Process form submission for moves
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'move' && isset($_POST['from']) && isset($_POST['to']) && isset($_POST['fen'])) {
        // Get current game state
        $game_state = getGameState($game_state_file);
        
        // Update game state with the new move
        $game_state['lastMove'] = [
            'from' => $_POST['from'],
            'to' => $_POST['to']
        ];
        
        // Store the complete FEN notation (captures the entire board state)
        $game_state['fen'] = $_POST['fen'];
        
        // Check if game is over after this move
        if (isset($_POST['game_status'])) {
            $game_state['game_status'] = $_POST['game_status'];
            
            // If the game is over (checkmate), record the win automatically
            if ($_POST['game_status'] === 'checkmate') {
                $score = getScore($score_file);
                // The winner is opposite of the current turn (who just got checkmated)
                if ($_POST['checkmated'] === 'w') { // White is checkmated, Black (Dad) wins
                    $score['dad']++;
                } else { // Black is checkmated, White (Quentin) wins
                    $score['quentin']++;
                }
                saveScore($score_file, $score);
                $game_state['winner'] = ($_POST['checkmated'] === 'w') ? 'black' : 'white';
            }
        }
        
        // Update timer if turn was active
        if ($game_state['turnActive'] && $game_state['turnStartTime'] > 0) {
            $current_time = time();
            $elapsed_time = $current_time - $game_state['turnStartTime'];
            
            // Add elapsed time to the appropriate player's total
            if ($game_state['currentTurn'] === 'white') {
                $game_state['whiteTimeTotal'] += $elapsed_time;
            } else {
                $game_state['blackTimeTotal'] += $elapsed_time;
            }
            
            // Reset turn timer
            $game_state['turnActive'] = false;
            $game_state['turnStartTime'] = 0;
        }
        
        // Toggle turn
        $game_state['currentTurn'] = ($game_state['currentTurn'] === 'white') ? 'black' : 'white';
        
        // Save the updated game state
        saveGameState($game_state_file, $game_state);
    } elseif ($action === 'start_turn') {
        // Start the turn timer
        $game_state = getGameState($game_state_file);
        
        // Update the turn timer
        $game_state['turnActive'] = true;
        $game_state['turnStartTime'] = time();
        
        // Save the updated game state
        saveGameState($game_state_file, $game_state);
    } elseif ($action === 'reset') {
        // Reset the game
        initializeGameState($game_state_file);
    // Capture handling code has been removed
    } elseif ($action === 'record_win' && isset($_POST['winner'])) {
        // Record a win for the specified player
        $score = getScore($score_file);
        if ($_POST['winner'] === 'white') {
            $score['quentin']++;
        } else {
            $score['dad']++;
        }
        saveScore($score_file, $score);
        // Reset the game after recording the win
        initializeGameState($game_state_file);
    } elseif ($action === 'reset_score' && isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
        // Reset the score if confirmed
        initializeScore($score_file);
    }
    
    // Redirect to prevent form resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Initialize game state if it doesn't exist
if (!file_exists($game_state_file)) {
    initializeGameState($game_state_file);
}

// Initialize score if it doesn't exist
if (!file_exists($score_file)) {
    initializeScore($score_file);
}

// Get current game state
$game_state = getGameState($game_state_file);

// Get current score
$score = getScore($score_file);

// Helper functions
function initializeGameState($file) {
    $initial_state = [
        'currentTurn' => 'white',
        'lastMove' => null,
        'fen' => 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1', // Starting position
        'game_status' => 'active',
        'winner' => null,
        'whiteTimeTotal' => 0, // Total accumulated time for white (Quentin) in seconds
        'blackTimeTotal' => 0, // Total accumulated time for black (Dad) in seconds
        'turnStartTime' => 0,  // When the current turn started (Unix timestamp)
        'turnActive' => false  // Whether the turn timer is currently active
    ];
    saveGameState($file, $initial_state);
}

function getGameState($file) {
    if (file_exists($file)) {
        $json_data = file_get_contents($file);
        $state = json_decode($json_data, true);
        if (!$state || !isset($state['fen'])) {
            // If the state is corrupted or missing, initialize a new one
            initializeGameState($file);
            return getGameState($file);
        }
        return $state;
    }
    return null;
}

function saveGameState($file, $state) {
    file_put_contents($file, json_encode($state));
}

function initializeScore($file) {
    $initial_score = [
        'quentin' => 0,
        'dad' => 0
    ];
    saveScore($file, $initial_score);
}

function getScore($file) {
    if (file_exists($file)) {
        $json_data = file_get_contents($file);
        $score = json_decode($json_data, true);
        if (!$score || !isset($score['quentin']) || !isset($score['dad'])) {
            // If the score is corrupted or missing, initialize a new one
            initializeScore($file);
            return getScore($file);
        }
        return $score;
    }
    return null;
}

function saveScore($file, $score) {
    file_put_contents($file, json_encode($score));
}

// Time formatting function
function formatTime($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    
    if ($hours > 0) {
        return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
    } else {
        return sprintf('%d:%02d', $minutes, $secs);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dad and Quentin's Chess Game</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/chess.js/0.10.3/chess.min.js"></script>
    <script src="https://unpkg.com/@chrisoakman/chessboardjs@1.0.0/dist/chessboard-1.0.0.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/@chrisoakman/chessboardjs@1.0.0/dist/chessboard-1.0.0.min.css">
    
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 100%;
            margin: 0 auto;
            padding: 0;
            text-align: center;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0;
        }
        #board {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
        }
        #game-info {
            margin-top: 20px;
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
        }
        /* Captured pieces CSS removed */
        #status {
            font-weight: bold;
            margin: 10px 0;
            font-size: 22px;
            color: #2c3e50;
        }
        
        /* Timer styling */
        .timer-container {
            display: flex;
            justify-content: space-around;
            margin: 10px 0;
        }
        
        .timer {
            text-align: center;
            padding: 5px;
            border-radius: 5px;
            width: 45%;
        }
        
        .white-timer {
            background-color: #f1f1f1;
            border: 1px solid #ddd;
        }
        
        .black-timer {
            background-color: #333;
            color: white;
        }
        
        .timer-label {
            font-weight: bold;
            margin-bottom: 2px;
            font-size: 14px;
        }
        
        .timer-value {
            font-size: 20px;
            font-family: monospace;
        }
        
        /* Start turn button */
        #start-turn-button {
            background-color: #2980b9;
            font-size: 16px;
            padding: 8px 15px;
            margin: 5px;
        }
        
        #start-turn-button:hover {
            background-color: #3498db;
        }
        
        .controls {
            margin: 5px 0;
        }
        
        /* Active timer indication */
        .timer-active {
            box-shadow: 0 0 10px rgba(46, 204, 113, 0.8);
            animation: pulse 2s infinite;
        }
        .winner-status {
            font-size: 26px;
            color: #e74c3c;
        }
        .controls {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        button {
            padding: 8px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 5px;
        }
        button:hover {
            background-color: #45a049;
        }
        /* Captured pieces styling removed */
        .instructions {
            margin-top: 10px;
            padding: 8px;
            background-color: #f5f5f5;
            border-radius: 4px;
            font-size: 14px;
        }
        .score-container {
            margin-top: 10px;
            padding: 10px;
            background-color: #eaf7ea;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .score-title {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        .score-board {
            display: flex;
            justify-content: space-around;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        .player-score {
            text-align: center;
            width: 45%;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .quentin-score {
            background-color: #f1f1f1;
            border: 1px solid #ddd;
        }
        .dad-score {
            background-color: #333;
            color: white;
            border: 1px solid #222;
        }
        .player-name {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 5px;
        }
        .score-value {
            font-size: 24px;
            font-weight: bold;
        }
        .reset-score-container {
            margin-top: 10px;
        }
        .win-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        #modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            z-index: 1000;
        }
        #modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            text-align: center;
            width: 300px;
            max-width: 90%;
        }
        .modal-buttons {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .cancel-button {
            background-color: #e74c3c;
        }
        .cancel-button:hover {
            background-color: #c0392b;
        }
        
        /* Square highlighting styles for click-to-move */
        .square-highlight {
            box-shadow: inset 0 0 3px 3px yellow !important;
            background-color: rgba(255, 255, 0, 0.4) !important;
        }
        
        .square-legal-move {
            box-shadow: inset 0 0 3px 3px #7ef77e !important;
            background-color: rgba(0, 255, 0, 0.3) !important;
            cursor: pointer !important;
        }
        
        /* Pulsing animation for refresh button */
        @keyframes pulse {
            0% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(52, 152, 219, 0.7);
            }
            
            70% {
                transform: scale(1.05);
                box-shadow: 0 0 0 10px rgba(52, 152, 219, 0);
            }
            
            100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(52, 152, 219, 0);
            }
        }
        
        /* Refresh reminder styling */
        #refresh-reminder {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
            display: none;
        }
        
        /* Better touch targeting for mobile */
        @media (max-width: 500px) {
            .square-55d63 {
                touch-action: manipulation; /* Improve touch handling */
            }
            .square-highlight {
                background-color: rgba(255, 255, 0, 0.6) !important; /* Brighter for mobile */
            }
            .square-legal-move {
                background-color: rgba(0, 255, 0, 0.5) !important; /* Brighter for mobile */
            }
        }

        /* For better touch interaction on mobile */
        @media (max-width: 500px) {
            .square-55d63 {
                min-height: 40px !important;
            }
        }
        
        /* For debug purposes */
        .debug-info {
            margin-top: 20px;
            padding: 10px;
            border: 1px solid #ccc;
            background-color: #f9f9f9;
            text-align: left;
            font-family: monospace;
            display: none;
        }
        
        /* Media queries for better mobile support */
        @media (max-width: 500px) {
            #board {
                width: 95% !important; /* Set width to 95% of container */
                max-width: 300px !important; /* Maximum width on mobile */
                margin: 0 auto !important;
            }
            /* Captured pieces media query removed */
            .player-score {
                width: 100%;
                margin-bottom: 5px;
            }
            #status {
                font-size: 20px;
                margin: 8px 0;
            }
            .winner-status {
                font-size: 22px;
            }
            /* Make sure the entire board fits on screen */
            .container {
                padding: 5px;
                width: 100%;
                box-sizing: border-box;
            }
            .instructions p {
                margin: 5px 0;
            }
            .score-board {
                margin-bottom: 5px;
            }
            .win-buttons {
                margin-top: 5px;
            }
            h2 {
                margin-top: 0;
                margin-bottom: 5px;
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 style="margin-top: 0; margin-bottom: 8px;">Quentin and Dad's Chess Game!</h2>
        <div id="board"></div>
        <div id="status" class="<?php echo (isset($game_state['game_status']) && $game_state['game_status'] === 'checkmate') ? 'winner-status' : ''; ?>" style="margin-bottom: 5px;">
            <?php
            $turn_text = "";
            if (isset($game_state['game_status']) && $game_state['game_status'] === 'checkmate') {
                $winner = isset($game_state['winner']) && $game_state['winner'] === 'white' ? "Quentin" : "Dad";
                $turn_text = "Game Over! " . $winner . " Won!";
            } else {
                if ($game_state['currentTurn'] === 'white') {
                    $turn_text = "White - Quentin's Turn";
                } else {
                    $turn_text = "Black - Dad's Turn";
                }
            }
            echo $turn_text;
            ?>
        </div>
        
        <!-- Start Turn button placed above timers -->
        <div class="controls" style="margin-top: 0; margin-bottom: 8px;">
            <form method="post" id="start-turn-form" style="margin: 0;">
                <input type="hidden" name="action" value="start_turn">
                <button type="submit" id="start-turn-button" style="width: 95%; max-width: 350px; font-size: 18px; font-weight: bold;">START TURN</button>
            </form>
        </div>
        
        <!-- Timer container -->
        <div class="timer-container">
            <div class="timer white-timer">
                <div class="timer-label">Quentin's Time</div>
                <div class="timer-value" id="white-timer"><?php echo formatTime($game_state['whiteTimeTotal']); ?></div>
            </div>
            <div class="timer black-timer">
                <div class="timer-label">Dad's Time</div>
                <div class="timer-value" id="black-timer"><?php echo formatTime($game_state['blackTimeTotal']); ?></div>
            </div>
        </div>
        <br>
        <div class="score-container">
            <div class="score-title">Overall Game Score</div>
            <div class="score-board">
                <div class="player-score quentin-score">
                    <div class="player-name">Quentin's Wins</div>
                    <div class="score-value"><?php echo $score['quentin']; ?></div>
                </div>
                <div class="player-score dad-score">
                    <div class="player-name">Dad's Wins</div>
                    <div class="score-value"><?php echo $score['dad']; ?></div>
                </div>
            </div>
            
            <!-- Win buttons removed -->
            
            <div class="reset-controls" style="display: flex; justify-content: center; gap: 10px; margin-top: 10px;">
                <form id="reset-form" method="post" style="margin: 0;">
                    <input type="hidden" name="action" value="reset">
                    <button type="submit">Reset Game</button>
                </form>
                
                <button onclick="showResetConfirmation()">Reset Scores</button>
            </div>
        </div>
        
        <div class="instructions">
            <p>Hello Quentin! I programed this super simple chess game for us. I made it so anyone can play the next move to keep it simple.</p>
            <p>All it does it keep track of the positions and each player's time, we just have to trust each other to only move our own peices :)</p>
            <p><strong>VERY IMPORTANT:</strong> I love you, Quentin and you and an amazing person! Please keep being the best you, you can be!
            <p><strong>To play:</strong> Just click the "Start Turn" button to begin your turn then you can move. This will refresh the board and start time timer.Each piece once selected will show its legal moves.</p>
        </div>
        
        <!-- Debug information (hidden by default) -->
        <div class="debug-info">
            <h3>Debug Info:</h3>
            <pre id="debug-output"></pre>
        </div>
        
        <!-- Hidden forms for game actions -->
        <form id="move-form" method="post" style="display:none;">
            <input type="hidden" name="action" value="move">
            <input type="hidden" name="from" id="move-from">
            <input type="hidden" name="to" id="move-to">
            <input type="hidden" name="fen" id="move-fen">
            <input type="hidden" name="game_status" id="game-status">
            <input type="hidden" name="checkmated" id="checkmated-color">
        </form>
        
        <!-- Capture form removed -->
        
        <!-- Modal for confirming score reset -->
        <div id="modal">
            <div id="modal-content">
                <h3>Reset Score History</h3>
                <p>Are you sure you want to reset the entire score history? This cannot be undone.</p>
                <div class="modal-buttons">
                    <form method="post">
                        <input type="hidden" name="action" value="reset_score">
                        <input type="hidden" name="confirm" value="yes">
                        <button type="submit">Yes, Reset Score</button>
                    </form>
                    <button onclick="hideResetConfirmation()" class="cancel-button">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Manual refresh function for the refresh button
        function forceRefresh() {
            console.log("Manual refresh requested");
            // Use timestamp to force a fresh load from server
            window.location.href = window.location.href.split('?')[0] + '?t=' + Date.now();
        }

        // Auto-refresh has been removed
        
        // Initialize chess.js and chessboard.js
        var board = null;
        var game = new Chess('<?php echo $game_state['fen']; ?>');
        var $status = $('#status');
        
        // Timer variables
        var turnActive = <?php echo $game_state['turnActive'] ? 'true' : 'false'; ?>;
        var timerInterval = null;
        var startTime = <?php echo $game_state['turnStartTime']; ?>;
        var whiteTimeTotal = <?php echo $game_state['whiteTimeTotal']; ?>;
        var blackTimeTotal = <?php echo $game_state['blackTimeTotal']; ?>;
        var currentTurn = '<?php echo $game_state['currentTurn']; ?>';
        
        // For debugging
        function updateDebugInfo() {
            $('#debug-output').text(
                'FEN: ' + game.fen() + '\n' +
                'Turn: ' + game.turn() + '\n' +
                'In Check: ' + game.in_check() + '\n' +
                'In Checkmate: ' + game.in_checkmate() + '\n' +
                'In Draw: ' + game.in_draw() + '\n' +
                'Current PHP Turn: ' + currentTurn
            );
        }
        
        // Track selected piece for click-to-move style
        var selectedSquare = null;
        
        function onDragStart(source, piece, position, orientation) {
            // Only allow the current player to move their pieces if turn is active
            if (!turnActive || // Turn must be active (Start Turn clicked)
                (game.turn() === 'w' && piece.search(/^b/) !== -1) ||
                (game.turn() === 'b' && piece.search(/^w/) !== -1) ||
                (game.turn() === 'w' && currentTurn !== 'white') ||
                (game.turn() === 'b' && currentTurn !== 'black') ||
                game.in_checkmate() || 
                game.in_draw()) {
                return false;
            }
        }
        
        // Handle square click for click-to-select, click-to-move functionality
        function onSquareClick(square) {
            // Don't allow clicks if turn is not active
            if (!turnActive) return;
            
            // Get the piece on this square
            var piece = game.get(square);
            
            // If we already have a square selected
            if (selectedSquare !== null) {
                // Try to make a move from the previously selected square to this square
                var move = game.move({
                    from: selectedSquare,
                    to: square,
                    promotion: 'q' // Always promote to a queen for simplicity
                });
                
                // Remove all highlights and reset selection
                removeHighlights();
                
                // If the move is illegal, highlight the new selection if it's a valid piece
                if (move === null) {
                    // If the new square has a piece of the current player, select it
                    if (piece && 
                        ((game.turn() === 'w' && piece.color === 'w') || 
                         (game.turn() === 'b' && piece.color === 'b'))) {
                        selectedSquare = square;
                        highlightSquare(square);
                        highlightLegalMoves(square);
                    } else {
                        selectedSquare = null;
                    }
                    return;
                }
                
                // If move was made successfully
                selectedSquare = null;
                
                // Capture handling removed
                
                // Check game status
                var gameStatus = 'active';
                var checkmatedColor = '';
                
                if (game.in_checkmate()) {
                    gameStatus = 'checkmate';
                    checkmatedColor = game.turn(); // The side that is to move is checkmated
                } else if (game.in_draw()) {
                    gameStatus = 'draw';
                }
                
                // Submit the move form with FEN notation and game status
                $('#move-from').val(move.from);
                $('#move-to').val(move.to);
                $('#move-fen').val(game.fen());
                $('#game-status').val(gameStatus);
                $('#checkmated-color').val(checkmatedColor);
                $('#move-form').submit();
                
                // Update the board position
                board.position(game.fen());
                updateStatus();
                updateDebugInfo();
                
                // Stop the timer when move is made
                stopTimer();
                
                return;
            }
            
            // No square was selected yet, so select this one if it has a movable piece
            if (piece && 
                ((game.turn() === 'w' && piece.color === 'w' && currentTurn === 'white') || 
                 (game.turn() === 'b' && piece.color === 'b' && currentTurn === 'black'))) {
                selectedSquare = square;
                highlightSquare(square);
                highlightLegalMoves(square);
            }
        }
        
        // Highlight a square
        function highlightSquare(square) {
            $('.square-' + square).addClass('square-highlight');
        }
        
        // Highlight legal move squares
        function highlightLegalMoves(square) {
            // Get all legal moves from this square
            var moves = game.moves({
                square: square,
                verbose: true
            });
            
            // Highlight each legal move square
            for (var i = 0; i < moves.length; i++) {
                $('.square-' + moves[i].to).addClass('square-legal-move');
            }
            
            // Log for debugging
            console.log('Legal moves from ' + square + ':', moves.map(function(m) { return m.to; }));
        }
        
        // Remove all highlights
        function removeHighlights() {
            $('.square-55d63').removeClass('square-highlight');
            $('.square-55d63').removeClass('square-legal-move');
        }
        
        function onDrop(source, target) {
            // See if the move is legal
            var move = game.move({
                from: source,
                to: target,
                promotion: 'q' // Always promote to a queen for simplicity
            });

            // Illegal move
            if (move === null) return 'snapback';
            
            // Capture handling removed
            
            // Check game status
            var gameStatus = 'active';
            var checkmatedColor = '';
            
            if (game.in_checkmate()) {
                gameStatus = 'checkmate';
                checkmatedColor = game.turn(); // The side that is to move is checkmated
            } else if (game.in_draw()) {
                gameStatus = 'draw';
            }
            
            // Submit the move form with FEN notation and game status
            $('#move-from').val(source);
            $('#move-to').val(target);
            $('#move-fen').val(game.fen());
            $('#game-status').val(gameStatus);
            $('#checkmated-color').val(checkmatedColor);
            $('#move-form').submit();
            
            updateStatus();
            updateDebugInfo();
            
            // Stop the timer when move is made
            stopTimer();
        }

        function onSnapEnd() {
            board.position(game.fen());
        }

        function updateStatus() {
            var status = '';
            
            if (game.in_checkmate()) {
                var winner = game.turn() === 'b' ? "Quentin" : "Dad";
                status = 'Game over! ' + winner + ' won!';
                $status.addClass('winner-status');
            } else if (game.in_draw()) {
                status = 'Game over, drawn position';
                $status.addClass('winner-status');
            } else {
                if (game.turn() === 'w') {
                    status = "Quentin's (White) Turn";
                    if (game.in_check()) {
                        status += " - IN CHECK!";
                    }
                } else {
                    status = "Dad's (Black) Turn";
                    if (game.in_check()) {
                        status += " - IN CHECK!";
                    }
                }
                $status.removeClass('winner-status');
            }

            $status.html(status);
        }

        // Show/Hide the reset score confirmation modal
        function showResetConfirmation() {
            document.getElementById('modal').style.display = 'block';
        }
        
        function hideResetConfirmation() {
            document.getElementById('modal').style.display = 'none';
        }
        
        // Calculate board width based on screen size
        function calculateBoardWidth() {
            var containerWidth = $('.container').width();
            // For mobile (if screen width is less than 500px)
            if (window.innerWidth <= 500) {
                return Math.min(containerWidth * 0.9, 300); // 90% of container up to 300px max
            } else {
                return Math.min(containerWidth - 40, 400); // Maximum of 400px for desktop
            }
        }

        // Responsive layout adjustments
        function adjustLayout() {
            if (board) {
                // Just call resize on the board
                board.resize();
            }
        }

        // Initialize the chessboard with responsive config
        function initBoard() {
            var boardWidth = calculateBoardWidth();
            
            // Check if we're on mobile
            var isMobile = window.innerWidth <= 500 || 'ontouchstart' in window;
            
            var config = {
                // Disable dragging on mobile since we'll use click-to-move only
                draggable: !isMobile,
                position: game.fen(),
                onDragStart: onDragStart,
                onDrop: onDrop,
                onSnapEnd: onSnapEnd,
                onSquareClick: onSquareClick,  // Add click-to-move functionality
                pieceTheme: 'images/chesspieces/{piece}.png',
                showNotation: true,
                width: boardWidth
            };
            
            // Create a temporary div to test if images are loading
            $("<div>").addClass("image-test")
                .css({
                    position: "absolute", 
                    visibility: "hidden",
                    backgroundImage: "url('images/chesspieces/wP.png')"
                })
                .appendTo("body");
                
            // Check if local images are available
            var img = new Image();
            img.onload = function() {
                // Local image loaded successfully, continue with local config
                board = Chessboard('board', config);
                
                // Add listeners for square clicks and touch events
                $('.square-55d63').on('click touchend', function(e) {
                    // Prevent default touch action to avoid scrolling
                    if (e.type === 'touchend') {
                        e.preventDefault();
                    }
                    
                    var square = $(this).attr('data-square');
                    if (square) {
                        onSquareClick(square);
                    }
                });
                
                // Explicitly disable dragging on mobile devices
                if (window.innerWidth <= 500 || 'ontouchstart' in window) {
                    // Disable dragging by removing handlers
                    board.config.draggable = false;
                }
                
                adjustLayout();
                updateStatus();
                updateDebugInfo();
                setupAutoRefresh();
                
                // Add instructions for mobile users
                $('.instructions').prepend(
                    '<p><strong>Mobile users:</strong> Tap a piece to select it (it will highlight), ' +
                    'then tap the destination square to move it.</p>'
                );
            };
            img.onerror = function() {
                // Local image failed to load, use online images instead
                config.pieceTheme = 'https://unpkg.com/@chrisoakman/chessboardjs@1.0.0/img/chesspieces/wikipedia/{piece}.png';
                board = Chessboard('board', config);
                
                // Add listeners for square clicks and touch events
                $('.square-55d63').on('click touchend', function(e) {
                    // Prevent default touch action to avoid scrolling
                    if (e.type === 'touchend') {
                        e.preventDefault();
                    }
                    
                    var square = $(this).attr('data-square');
                    if (square) {
                        onSquareClick(square);
                    }
                });
                
                // Explicitly disable dragging on mobile devices
                if (window.innerWidth <= 500 || 'ontouchstart' in window) {
                    // Disable dragging by removing handlers
                    board.config.draggable = false;
                }
                
                adjustLayout();
                updateStatus();
                updateDebugInfo();
                setupAutoRefresh();
                
                // Add instructions for mobile users
                $('.instructions').prepend(
                    '<p><strong>Mobile users:</strong> Tap a piece to select it (it will highlight), ' +
                    'then tap the destination square to move it.</p>'
                );
                
                console.log("Using online images as fallback");
            };
            img.src = 'images/chesspieces/wP.png';
        }
        
        // Handle window resize
        $(window).resize(function() {
            if (board !== null) {
                adjustLayout();
            }
        });
        
        // Timer functions
        function formatTime(seconds) {
            var hours = Math.floor(seconds / 3600);
            var minutes = Math.floor((seconds % 3600) / 60);
            var secs = seconds % 60;
            
            if (hours > 0) {
                return hours + ':' + (minutes < 10 ? '0' : '') + minutes + ':' + (secs < 10 ? '0' : '') + secs;
            } else {
                return minutes + ':' + (secs < 10 ? '0' : '') + secs;
            }
        }
        
        function updateTimer() {
            if (!turnActive) return;
            
            var currentTime = Math.floor(Date.now() / 1000);
            var elapsedTime = currentTime - startTime;
            
            if (currentTurn === 'white') {
                var totalTime = whiteTimeTotal + elapsedTime;
                $('#white-timer').text(formatTime(totalTime));
            } else {
                var totalTime = blackTimeTotal + elapsedTime;
                $('#black-timer').text(formatTime(totalTime));
            }
        }
        
        function startTimer() {
            turnActive = true;
            startTime = Math.floor(Date.now() / 1000);
            
            // Highlight the active timer
            if (currentTurn === 'white') {
                $('.white-timer').addClass('timer-active');
                $('.black-timer').removeClass('timer-active');
            } else {
                $('.black-timer').addClass('timer-active');
                $('.white-timer').removeClass('timer-active');
            }
            
            // Update the timer every second
            timerInterval = setInterval(updateTimer, 1000);
        }
        
        function stopTimer() {
            turnActive = false;
            clearInterval(timerInterval);
            $('.timer').removeClass('timer-active');
        }
        
        // Initialize the board when document is ready
        $(document).ready(function() {
            initBoard();
            
            // Initialize timer if it's active
            if (turnActive && startTime > 0) {
                startTimer();
            }
            
            // Disable board interaction until Start Turn is clicked
            if (!turnActive) {
                board.position(game.fen(), false);
                $('#board .square-55d63').css('pointer-events', 'none');
                
                // Set button text based on game state
                var buttonText = 'Start ' + (currentTurn === 'white' ? 'Quentin\'s' : 'Dad\'s') + ' Turn';
                $('#start-turn-button').text(buttonText);
                
                // Handle start turn form submission
                $('#start-turn-form').on('submit', function() {
                    // Form will be submitted and page refreshed, but we can show a visual indicator
                    $('#start-turn-button').prop('disabled', true).text('Starting turn...');
                    return true; // Allow form submission
                });
            } else {
                // Turn is already active
                $('#start-turn-button').prop('disabled', true).text('Turn in progress...');
                $('#board .square-55d63').css('pointer-events', 'auto');
            }
            
            // Close modal when clicking outside of it
            $(window).click(function(event) {
                if (event.target == document.getElementById('modal')) {
                    hideResetConfirmation();
                }
            });
            
            // Escape key to close modal
            $(document).keydown(function(event) {
                if (event.key === "Escape") {
                    hideResetConfirmation();
                }
            });
        });
    </script>
</body>
</html>