<?php

function connect() {
	$conn = mysql_connect(localhost,abowyer_fastest,jeantalon,false);
	if ($conn) {
		if (mysql_select_db('abowyer_fastest') == FALSE) {
			echo 'select failure';
		} else {
			echo 'connected';
		}
	} else {
		echo 'connect failure';
	}	
	return $conn;
}

function disconnect($ref) {
	$success = mysql_close($ref);
	if (!$success)
		printerror('disconnect_failure', "");
	return $success;
}

function runSQL($query) {
	$result = mysql_query($query);
	if (!$result)
		echo 'query error';
	    print_r(mysql_error());
	return $result;
}

function getState() {
	runSQL("SELECT state FROM state WHERE n=1;");
}

function getCurrentQuestion() {
	runSQL("SELECT question_number FROM state WHERE n=1;");
}


function getNumberOfPlayersAnsweringThisQuestion() {
	runSQL("SELECT number_of_players FROM state WHERE n=1;");
}

function getQuestionText($question_number) {
	$question_text = runSQL("SELECT question_text FROM questions WHERE question_number=".$question_number.";");
	return $question_text;
}

function setState($new_state,$question_number=NULL,$number_of_players=NULL) {
	$query = 'UPDATE state SET state="'.$new_state.'"';
	if ($question_number) {
		if ($number_of_players) {
			$query.=", question_number=".$question_number;
		}
		$query.=", number_of_players=".$number_of_players;
	}
	$query.=" WHERE n=1";
	runSQL($query);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$query = "INSERT IGNORE INTO players(player_name) VALUES('posted');";
	runSQL($query);
} else {
	$url = $_SERVER['REQUEST_URI'];
	$url_parts = split("/",$url);
	if (count($url_parts)==3) {
		$action = $url_parts[1];
		$detail = $url_parts[2];
	}

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

	switch ($action) {
		case "view": {

		    // poll and check state - only do something if state has changed OR question has changed.
			  // if "registration" show the registered players by name as they register.
		      // if "ready" then show "Question X - get ready" and the question [X from extra field on state] (host will read it out)
		      // if "answering" then show The question and the possible answers and a message to "Make your selections now"
		      // if "review" then show the Question and the correct answers. Show a button to show times.
				 // on "show times" show the times (but not who was correct). Show a Reveal button
		         // on "reveal" grey out all the wrong answers and award points to the players based on who was first.
			  // if "scores" show a summary scores for all questions - points for each person this round.
		      break;
		}
		case "host": {
			// poll and check state
				// if "review" display a "next question " (which will say "show final scores" if last question)
				// if "ready" display a "GO" button which sets state to "answering"
						// record start time (as NULL player) set state to "answering" and extra field to question number 
																				   // and second extra field to number of players for this q.
				// if "registration" display a "first question" button (which will set to ready:1")

			// following buttons are displayed all the time:

			// display button to go to question
			  // on ready - set state to "ready" and extra field to question number. wipe all answers for that question (inc the NULL one)

			// display a reset button
			  // on reset, set state to "registration" and wipe times and players tables BUT NOT answers

			break;
		}
		case "play": {
			echo "playing as ".$detail;

			$state = getState();

			// initial serverside/renderbehaviour (from PHP):
			switch (state) {
				case 'registration': {
					// on initial load, register the player into the players table
					$query = "INSERT IGNORE INTO players(player_name) VALUES('".$detail."');";
					runSQL($query);
					return "<h2>Registered</h2><p>Please wait: Other players are registering";
					// TODO render JS with the following client side behaviour:
					   // poll and check state
					      // if state is still registration
				  	      // retrieve and update display with the list of players
					break;	
				}
				case 'ready':
					//$question_number = getCurrentQuestion();
					return "<h2>Question X</h2><p>Get ready to put the following items in age order.</p>";
					// TODO render JS with the following client side behaviour:
					   // poll and check state
					break;
				case 'answering':
					//$question_number = getCurrentQuestion();
					return "<h2>Question X</h2><p>Put the following items in age order:</p><p><a>A</a>&nbsp;&nbsp;&nbsp;<a>A</a></p>";


				default: {
					// do nothing
				}	  
			}

			// client side post load behaviour (from JS):
	        





				// if state is ready
				// display "Question X (but not the question or the options)" [X from extra field on state]

			    // if state is answering
				// display "Question X (answer now)" [X from extra field on state] and show four buttons
					// as each button is pressed, disable it and prep the response
			        // on the fourth press, submit to server the choices [ server records the time, and checks if this is the last player, and if so, advances to "review"]

			    // if state is review
					// display "Question X is finished. Please wait for the next question"

				// if state is ending
					// display "All done - thanks for playing."











			break;
		}
	    default: {
	    	echo "<h2>ERROR</h2><p>You have entered an invalid web address.</p>";
	    }
	}

	/*$ref = connect();
	if ($ref) {
		$url = $_SERVER['REQUEST_URI'];
		$base = basename($url);
		$query = "INSERT IGNORE INTO players(player_name) VALUES('".$base."');";
		$res = runSQL($query);
		disconnect($ref);
	}*/
}
?>