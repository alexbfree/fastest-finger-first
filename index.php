<?php

global $conn;

function connect()
{
    $conn = mysqli_connect(localhost, abowyer_fastest, jeantalon, 'abowyer_fastest');
    if (!$conn) {
        echo 'connect failure';
    }
    return $conn;
}

function disconnect($ref)
{
    $success = mysqli_close($ref);
    if (!$success)
        printerror('disconnect_failure', "");
    return $success;
}

function runSQL($query)
{
    global $conn;
    $result = mysqli_query($conn, $query);
    if (!$result) {
        echo 'query error';
        print_r(mysqli_error($conn));
    }
    $response = array();
    while ($row = mysqli_fetch_assoc($result)) {
        array_push($response, $row);
    }
    return $response;
}

function runSQLPrepared($query_with_markers, $param)
{
    global $conn;
    $stmt = mysqli_prepare($conn, $query_with_markers);
    mysqli_stmt_bind_param($stmt, 's', $param);
    mysqli_stmt_execute($stmt);
    $data = "";
    mysqli_stmt_bind_result($stmt, $data);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    return $data;
}

function getState()
{
    $res = runSQL("SELECT state FROM state WHERE n=1;");
    return $res[0]['state'];
}

function getCurrentQuestion()
{
    $res = runSQL("SELECT question_number FROM state WHERE n=1;");
    return $res[0]['question_number'];
}


function getNumberOfPlayersAnsweringThisQuestion()
{
    $res = runSQL("SELECT number_of_players FROM state WHERE n=1;");
    return $res[0]['number_of_players'];
}

function getPlayerList()
{
    $res = runSQL("SELECT player_name FROM players;");
    $names = [];
    foreach ($res as $row) {
        array_push($names, $row['player_name']);
    }
    return $names;
}

function getQuestionText($question_number)
{
    $res = runSQL("SELECT question_text FROM questions WHERE question_number=" . $question_number . ";");
    return $res[0]['question_text'];;
}

function setState($new_state, $question_number = NULL, $number_of_players = NULL)
{
    $query = "UPDATE state SET state=$new_state;";
    if ($question_number) {
        if ($number_of_players) {
            $query .= ", question_number=" . $question_number;
        }
        $query .= ", number_of_players=" . $number_of_players;
    }
    $query .= " WHERE n=1";
    runSQL($query);
}

function stringArrayToJSArray($arr)
{
    $arr = array_map(function($word) { return ucwords($word); }, $arr);
    return '["' . implode('", "', $arr) . '"]';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo 'received post query';
    //$query = "INSERT IGNORE INTO players(player_name) VALUES(?);";
    //runSQLPrepared($query,'posted');
} else {
    $conn = connect();

    $url = $_SERVER['REQUEST_URI'];
    $url_parts = explode("/", $url);
    if (count($url_parts) == 3) {
        $action = $url_parts[1];
        $detail = $url_parts[2];
    }

    $state = getState();
    // state can be:
    //   "registration" - this is when players register
    //   "ready" - for a question - showing the question but not the options. [question number is set]
    //   "answering" - timer has started, people are answering [question number is set] [number of players answered is calculable]
    //   "review" - when the last answer is in, show the times of answers and results & points [question number is set]
    //   "scores" - show summary of points.

    // state transitions:
    //  "registration" -> "ready": Host advanced to 1 (by pressing ready:1 or "first question" button on host screen.
    //  "ready" -> "answering": Host has read it out then pressed "answer" button for this question. (number of players recorded)
    //  "answering" -> "review":  Each time a player submits an answer, the server checks if they were the last player. If so, then we shift to review state.
    //  "review" -> "ready": Host presses "Next question/end" button on the admin/host screen

    // only set if 'ready', 'answering' or 'review'
    $question_number = getCurrentQuestion();

    // only set if 'answering'
    $number_of_players = getNumberOfPlayersAnsweringThisQuestion();

    $player_list = getPlayerList();

    // render the webpage (state-specific variations are in index.html)
    $page_contents = file_get_contents("html/index.html");

    // put data into the page's javascript for client-side use
    $page_contents = str_replace("<POLL_URL>", '/poll/status', $page_contents);
    $page_contents = str_replace("<ACTION>", $action, $page_contents);
    $page_contents = str_replace("<STATE>", $state, $page_contents);
    if ($question_number) {
        $page_contents = str_replace("<QUESTION_NUMBER>", $question_number, $page_contents);
    } else {
        $page_contents = str_replace("<QUESTION_NUMBER>", 'null', $page_contents);
    }
    if ($question_number) {
        $page_contents = str_replace("<NUMBER_OF_PLAYERS>", $number_of_players, $page_contents);
    } else {
        $page_contents = str_replace("<NUMBER_OF_PLAYERS>", '0', $page_contents);
    }
    if ($player_list) {
        $page_contents = str_replace("<PLAYER_LIST>", stringArrayToJSArray($player_list), $page_contents);
    } else {
        $page_contents = str_replace("<PLAYER_LIST>", '[]', $page_contents);
    }

    $render_html = true;

    // server side state specific actions
    switch ($action) {
        case "poll":
        {
            if ($detail == 'status') {
                header('Content-Type: application/json');
                $data = new stdClass;
                $data->state = $state;
                $data->question_number = $question_number;
                $data->number_of_players = $number_of_players ? $number_of_players : 0;
                $data->player_list = $player_list;
                echo json_encode($data);
            }
            $render_html = false;
            break;
        }
        case "play":
        {
            if ($state == "registration" && $detail != "favicon.ico") {
                // player's name is in the $detail variable - register it in DB.

                // on initial load, register the player into the players table
                $query = "INSERT IGNORE INTO players (player_name) VALUES(?)";
                @runSQLPrepared($query, $detail); // warning suppressed as it works
            }
            break;
        }
        case "view":
        {
            break;
        }
        case "host":
        {
            // host
            break;
        }
        default:
        {
            echo "<h2>ERROR</h2><p>You have entered an invalid web address.</p>";
        }
    }
    disconnect($conn);

    if ($render_html) {
        // render the page to the browser
        echo $page_contents . "<!-- end of render -->";
    }
}
