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
$game->maxDepth = $opts['depth'] ?? 2;
$hist = [];
while (1) {
	echo " ===========\n" . $game->boardToString() . "\n";
	$ml = $game->moveList();
	$t0 = microtime(true);
	$suggested = [];
	$val = $game->suggest(list: $suggested);
	$dt = number_format(microtime(true) - $t0, 3);
	for ($upper = count($suggested) - 1; $upper > 0 && $suggested[$upper-1][1] == $suggested[$upper][1]; $upper--);
	echo implode("\n", array_map(fn($m) => implode(',', $m), $suggested)) . "\n$upper\n";
	$move = $suggested[rand($upper, count($suggested)-1)][0];
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
