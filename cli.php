<?php

require "./shogirnd.php";

$opts = [];
foreach ($argv as $arg) {
	$parts = explode('=', $arg, 2);
	if (count($parts) == 2)
		$opts[$parts[0]] = $parts[1];
}

$seed = $opts['seed'] ?? (time() % 1000000);
echo "Seed: $seed\n";
srand($seed);
$game = new \RandomShogi(6, 6, $opts['pos']??null);
$hist = [];
while (1) {
	echo " ===========\n" . $game->boardToString() . "\n";
	$t0 = microtime(true);
	$suggested = $game->suggestWithTimeout(5000);
	$dt = number_format(microtime(true) - $t0, 3);
	list($move, $val) = $suggested[array_rand($suggested)];
	echo "({$game->assess}) My move: " . implode(' ', $move) . " -> $val ({$dt}s)\n";
	$game->makeMove($move);
	$hist[] = $move;
	echo " ***********\n" . $game->boardToString() . "\n";
	$ml = $game->moveList();
	while (1) {
		echo "({$game->assess}) Your move: ";
		$move = explode(' ', trim(fgets(STDIN)));
		if ($move[0] == 'list') {
			echo implode("\n", array_map(fn($x) => implode(' ', $x), $game->moveList())) . "\n";
			continue;
		}
		if ($move[0] == 'back') {
			echo "Takeback!\n";
			$game->takeBack(array_pop($hist));
			$game->takeBack(array_pop($hist));
			$ml = $game->moveList();
			echo $game->boardToString() . "\n";
			continue;
		}
		foreach ($ml as $m) {
			$ok = 0;
			for ($i = 0; $i < 4; $i++)
				$ok += ($move[$i] == $m[$i]) ? 1 : 0;
			if ($ok == 4)
				break 2;
		}
		echo "Incorrect, let's retry!\n";
	}
	$game->makeMove($m);
	$hist[] = $m;
}
