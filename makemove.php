<?php

require __DIR__ . "/shogirnd.php";

$seed = getenv('shogi_seed');
if ($seed === false)
    $seed = time() % 1000000;
echo "Seed: $seed\n";
srand($seed);

$input = $argv[1] ?? fgets(STDIN);
if (trim($input) == 'init') {
	$game = new \RandomShogi(6, 6);
	echo $game->boardToSfen() . "\n";
	exit(0);
}

$game = new \RandomShogi(position: trim($input));

$mdd = getenv('shogi_drop_depth');
$game->maxDepthDrop = $mdd ? $mdd : 20;

$suggested = [];
$t0 = microtime(true);
$suggested = $game->suggestWithTimeout(5000);
$dt = number_format(microtime(true) - $t0, 3);

if (getenv('shogi_list_moves'))
	foreach ($suggested as $variant)
		echo implode(',', $variant[0]) . " -> {$variant[1]}\n";
$move = $suggested[array_rand($suggested)][0];

echo "curval: {$game->assess}\n";
echo "move: " . implode(' ', $move) . "\n";
$game->makeMove($move);

echo $game->boardToString() . "\n";

echo $game->boardToSfen() . "\n";

if (abs($game->assess) > 1000)
    exit(7);
