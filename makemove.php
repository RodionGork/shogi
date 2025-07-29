<?php

require __DIR__ . "/shogirnd.php";

$seed = getenv('shogi_seed');
if ($seed === false)
    $seed = time() % 1000000;
echo "Seed: $seed\n";
srand($seed);

$input = $argv[1] ?? fgets(STDIN);
$game = new \RandomShogi(position: trim($input));

$depth = getenv('shogi_depth');
$game->maxDepth = $depth ? $depth : 4;

$suggested = [];
$t0 = microtime(true);
$val = $game->suggest(list: $suggested);
$dt = number_format(microtime(true) - $t0, 3);

if (getenv('shogi_list_moves'))
	foreach ($suggested as $variant)
		echo implode(',', $variant[0]) . " -> {$variant[1]}\n";
for ($upper = count($suggested) - 1; $upper > 0 && $suggested[$upper-1][1] == $suggested[$upper][1]; $upper--);
$move = $suggested[rand($upper, count($suggested)-1)][0];

echo "curval: {$game->assess}\n";
echo "move: " . implode(' ', $move) . "\n";
$game->makeMove($move);

echo $game->boardToString() . "\n";

echo $game->boardToSfen() . "\n";

if (abs($game->assess) > 1000)
    exit(7);
