<?php

define("PIECES", "PXYRCVHSGK");

class RandomShogi {

	function __construct($cols=6, $rows=6, $position=null) {
		self::staticInit();
		$this->maxDepth = 1;
		if ($position)
			$this->mkSfenSetup($position);
		else {
			$this->cols = $cols;
			$this->rows = $rows;
			$this->mkField($cols, $rows);
			$this->mkRndSetup();
			$this->side = 0;
		}
		$this->assess = $this->assessPos();
	}

	function mkField($cols, $rows) {
		$this->field = [];
		for ($i = 0; $i < $rows+2; $i++) {
			$this->field[$i] = array_fill(0, $cols+2, '');
		}
		for ($i = 1; $i <= $rows; $i++)
			for ($j = 1; $j <= $cols; $j++)
				$this->field[$i][$j] = '-';
		$this->mask = $this->field;
		$this->pocket = [[], []];
		$this->pocketCnt = [0, 0];
	}

	function mkRndSetup() {
		$pieces = array_slice(str_split(PIECES, 1), 1, -1);
		shuffle($pieces);
		$pieces = array_slice($pieces, 0, $this->cols - 1);
		$pieces[] = 'K';
		shuffle($pieces);
		for ($i = 0; $i < $this->cols; $i++) {
			$this->field[1][$i+1] = $pieces[$i];
			$this->field[2][$i+1] = 'P';
			$this->field[$this->rows][$this->cols - $i] = strtolower($pieces[$i]);
			$this->field[$this->rows - 1][$this->cols - $i] = 'p';
		}
	}

	function mkSfenSetup($sfen) {
		$parts = explode(' ', $sfen);
		$board = str_replace(['+P', '+p'], ['T', 't'], $parts[0]);
		for ($i = 1; $i <= 9; $i++) {
			$board = str_replace("$i", str_repeat('-', $i), $board);
		}
		$pos = explode('/', $board);
		$this->rows = count($pos);
		$this->cols = strlen($pos[0]);
		$this->mkField($this->cols, $this->rows);
		for ($i = $this->rows; $i > 0; $i--)
			for ($j = 1; $j <= $this->cols; $j++)
				$this->field[$i][$j] = $pos[$this->rows - $i][$j-1];
		$this->side = ($parts[1]??'b') == 'w' ? 1 : 0;
		foreach (str_split($parts[2]??'', 1) as $piece) {
			if (!$piece) continue;
			$side = $piece > 'Z' ? 1 : 0;
			@$this->pocket[$side][$piece] += 1;
			$this->pocketCnt[$side] += 1;
		}
	}

	function assessPos() {
		$res = 0;
		for ($i = 1; $i <= $this->rows; $i++)
			for ($j = 1; $j <= $this->cols; $j++)
				$res -= self::$pieceValues[$this->field[$i][$j]]??0;
		foreach ($this->pocket as $pkt)
			foreach ($pkt as $piece => $count)
				$res -= self::$pieceValues[$piece] * $count;
		return $res;
	}

	function boardToString() {
		$res = [];
		for ($i = $this->rows; $i > 0; $i--) {
			$pkt = ($i - 1) / ($this->rows - 1);
			$suffix = ($pkt - floor($pkt)) ? '' : '   [ ' . $this->pocketToStr($pkt) . ' ]';
			$res[] = implode(' ', $this->field[$i]) . $suffix;
		}
		return implode("\n", $res);
	}

	function pocketToStr($side) {
		$res = [];
		foreach ($this->pocket[$side] as $piece => $count)
			if ($count)
				$res[] = "$piece*$count";
		return implode(' ', $res);	
	}

	function boardToSfen() {
		$board = array_map(fn($row) => implode('', $row), array_slice($this->field, 1, $this->rows));
		$board = implode('/', array_reverse($board));
		for ($i = 9; $i > 0; $i--)
			$board = str_replace(str_repeat('-', $i), "$i", $board);
		$board = str_replace(['T', 't'], ['+P', '+p'], $board);
		$side = $this->side ? 'w' : 'b';
		$pocket = '';
		foreach ($this->pocket as $pkt)
			foreach ($pkt as $piece => $cnt)
				$pocket .= str_repeat($piece, $cnt);
		return "$board $side $pocket";
	}

	function suggestWithTimeout($millis) {
		$this->maxDepth = 2;
		$this->searchNodes = 0;
		$this->deadline = microtime(true) + $millis / 1000;
		$results = $this->suggest();
		$depths = [];
		while (microtime(true) < $this->deadline) {
			$this->maxDepth++;
		    $this->searchNodes = 0;
			$resultsNew = $this->suggest()??[];
			for ($i = 0; $i < count($resultsNew); $i++) {
				$results[$i] = $resultsNew[$i];
				$depths[$i] = $this->maxDepth;
			}
		}
		$max = $results[0][1];
		$mul = 1 - 2 * $this->side;
		for ($i = 2; $i < count($results); $i++)
			if ($results[$i][1] * $mul > $max * $mul)
				$max = $results[$i][1];
		$filtered = [];
		foreach ($results as $res)
			if ($res[1] == $max)
				$filtered[] = $res;
		return $filtered;
	}

	function suggest($depth=1, $alpha=-1000000, $beta=1000000) {
		if ($this->searchNodes % 1000 == 999 && microtime(true) > $this->deadline)
			return NAN;
		$this->searchNodes++;
		$result = null;
		$ml = $this->moveList(true);
		$mul = 1 - $this->side * 2;
		$best = -1000000 * $mul;
		foreach ($ml as $move) {
			$this->makeMove($move);
			$val = $this->assess;
			$checkmate = stristr($move[4], 'K') !== false;
			if ($depth < $this->maxDepth && !$checkmate)
				$val = $this->suggest($depth+1, $alpha, $beta);
			$this->takeBack($move);
			if (is_nan($val)) {
				if ($depth > 1)
					return $val;
				else {
					break;
				}
			}
			if ($depth == 1)
				$result[] = [$move, $val];
			if ($this->side) {
				if ($val < $best) {
					$best = $val;
					if ($best < $alpha)
						$break;
					if ($best < $beta)
						$beta = $best;
				}
			} else {
				if ($val > $best) {
					$best = $val;
					if ($best > $beta)
						break;
					if ($best > $alpha)
						$alpha = $best;
				}
			}
		}
		return $depth == 1 ? $result : $best;
	}

	function makeMove($move) {
		list($r, $c, $r1, $c1, $taken) = $move;
		if ($r != '!') {
			$piece = $this->field[$r][$c];
			$this->field[$r][$c] = '-';
			if ($taken[0] == '^') {
				$this->assess += self::$pieceValues[$piece];
				$piece = $this->side ? 't' : 'T';
				$this->assess += self::$promBonus[$piece];
				$taken = $taken[1];
			}
			$this->field[$r1][$c1] = $piece;
			if ($taken != '-') {
				$this->assess += self::$pieceValues[$taken] * 2;
				if (($prom = (self::$promBonus[$taken]??0))) {
					$this->assess -= $prom;
					$taken = $this->side ? 'p' : 'P';
				} else
					$taken = chr(ord($taken) ^ 0x20);
				@$this->pocket[$this->side][$taken] += 1;
				$this->pocketCnt[$this->side] += 1;
			}
		} else {
			$this->field[$r1][$c1] = $c;
			$this->pocket[$this->side][$c] -= 1;
			$this->pocketCnt[$this->side] -= 1;
		}
		$this->side = 1 - $this->side;
	}

	function takeBack($move) {
		list($r, $c, $r1, $c1, $t) = $move;
		$this->side = 1 - $this->side;
		if ($r != '!') {
			$piece = $this->field[$r1][$c1];
			if ($t[0] == '^') {
				$t = $t[1];
				$this->assess -= self::$promBonus[$piece];
				$piece = $this->side ? 'p' : 'P';
				$this->assess -= self::$pieceValues[$piece];
			}
			if ($t != '-') {
				$this->assess -= self::$pieceValues[$t] * 2 - (self::$promBonus[$t] ?? 0);
				if ($t == 'T')
					$tt = 'p';
				elseif ($t == 't')
					$tt = 'P';
				else
					$tt = chr(ord($t) ^ 0x20);
				$this->pocket[$this->side][$tt] -= 1;
				$this->pocketCnt[$this->side] -= 1;
			}
			$this->field[$r][$c] = $piece;
		}else {
			$this->pocket[$this->side][$c] += 1;
			$this->pocketCnt[$this->side] += 1;
		}
		$this->field[$r1][$c1] = $t;
	}

	function moveList($strongMovesFirst=false) {
		$this->fillMask($this->side);
		$moves = [];
		for ($i = 1; $i <= $this->rows; $i++)
			for ($j = 1; $j <= $this->cols; $j++)
				if ($this->mask[$i][$j] == 'o')
					$this->addMoves($moves, $i, $j);
		if ($this->pocketCnt[$this->side]) {
			$pkt = [];
			foreach ($this->pocket[$this->side] as $p => $c)
				if ($c)
					$pkt[] = $p;
			for ($i = 1; $i <= $this->rows; $i++)
				for ($j = 1; $j <= $this->cols; $j++)
					if ($this->field[$i][$j] == '-')
						foreach ($pkt as $p)
							$moves[] = ['!', $p, $i, $j, '-'];
		}
		if ($strongMovesFirst) {
			$lt = 0;
			$rt = count($moves) - 1;
			while ($lt < $rt) {
				if ($moves[$lt][4] != '-')
					$lt++;
				elseif ($moves[$rt][4] == '-')
					$rt--;
				else {
					$t = $moves[$lt];
					$moves[$lt] = $moves[$rt];
					$moves[$rt] = $t;
				}
			}
		}
		return $moves;
	}

	function fillMask($side) {
		for ($i = 1; $i <= $this->rows; $i++)
			for ($j = 1; $j <= $this->cols; $j++) {
				$v = $this->field[$i][$j]; 
				$this->mask[$i][$j] = ($v == '-') ? '-' : ((($v > 'Z') == $side) ? 'o' : 'e');
			}
	}

	function addMoves(&$moves, $r, $c) {
		$piece = $this->field[$r][$c];
		$pat = self::$genPatterns[$piece] ?? null;
		if ($pat) {
			$this->genMoves($moves, $r, $c, $pat);
			return;
		}
		$piece = strtoupper($piece);
		if ($piece == 'P') {
			$this->pawnMoves($moves, $r, $c, 1 - 2 * $this->side);
		} else if ($piece == 'V') {
			$this->vertMoves($moves, $r, $c);
		} else if ($piece == 'H') {
			$this->horzMoves($moves, $r, $c);
		} else {
			exit("Unsupported piece: $piece");
		}
	}

	function horzMoves(&$moves, $r, $c) {
		for ($i = -1; $i <= 1; $i+=2) {
			$m = $this->mask[$r+$i][$c];
			if ($m == '-' || $m == 'e')
				$moves[] = [$r, $c, $r+$i, $c, $this->field[$r+$i][$c]];
		}
		for ($i = 1; 1; $i++) {
			$m = $this->mask[$r][$c-$i];
			if ($m == '-' || $m == 'e')
				$moves[] = [$r, $c, $r, $c-$i, $this->field[$r][$c-$i]];
			if ($m != '-') break;
		}
		for ($i = 1; 1; $i++) {
			$m = $this->mask[$r][$c+$i];
			if ($m == '-' || $m == 'e')
				$moves[] = [$r, $c, $r, $c+$i, $this->field[$r][$c+$i]];
			if ($m != '-') break;
		}
	}

	function vertMoves(&$moves, $r, $c) {
		for ($i = -1; $i <= 1; $i+=2) {
			$m = $this->mask[$r][$c+$i];
			if ($m == '-' || $m == 'e')
				$moves[] = [$r, $c, $r, $c+$i, $this->field[$r][$c+$i]];
		}
		for ($i = 1; 1; $i++) {
			$m = $this->mask[$r-$i][$c];
			if ($m == '-' || $m == 'e')
				$moves[] = [$r, $c, $r-$i, $c, $this->field[$r-$i][$c]];
			if ($m != '-') break;
		}
		for ($i = 1; 1; $i++) {
			$m = $this->mask[$r+$i][$c];
			if ($m == '-' || $m == 'e')
				$moves[] = [$r, $c, $r+$i, $c, $this->field[$r+$i][$c]];
			if ($m != '-') break;
		}
	}

	function pawnMoves(&$moves, $r, $c, $dir) {
		$r1 = $r + $dir;
		$m = $this->mask[$r1][$c];
		if ($m == '-' || $m == 'e') {
			$prom = ($r1 == 1 || $r1 == $this->rows) ? '^' : '';
			$moves[] = [$r, $c, $r1, $c, $prom . $this->field[$r1][$c]];
		}
	}

	function genMoves(&$moves, $r, $c, $pattern) {
		$pattern = (($pattern & 0xF0) << 1) | ($pattern &0x0F);
		for ($i = -1; $i <= 1; $i++)
			for ($j = -1; $j <= 1; $j++) {
				$m = $this->mask[$r+$i][$c+$j];
				if (($pattern & 1) && ($m == '-' || $m == 'e')) {
					$moves[] = [$r, $c, $r+$i, $c+$j, $this->field[$r+$i][$c+$j]];
				}
				$pattern >>= 1;
			}
	}

	static $genPatterns = [];
	static $pieceValues = [];
	static $promBonus = [];

	static function staticInit() {
		if (self::$genPatterns) return;
		self::$genPatterns = [
			'K' => 0b11111111, 'k' => 0b11111111,
			'G' => 0b11111010, 'g' => 0b01011111,
			'T' => 0b11111010, 't' => 0b01011111,
			'S' => 0b11100101, 's' => 0b10100111,
			'C' => 0b11100010, 'c' => 0b01000111,
			'R' => 0b01000101, 'r' => 0b10100010,
			'Y' => 0b10100010, 'y' => 0b01000101,
			'X' => 0b10100101, 'x' => 0b10100101,
		];
		self::$pieceValues = [
			'k' => 1000,
			'g' => 5,
			's' => 4,
			'c' => 3,
			'y' => 2,
			'r' => 2,
			'x' => 3,
			'v' => 4,
			'h' => 4,
			'p' => 1,
			't' => 1,
		];
		$ltrs = array_keys(self::$pieceValues);
		foreach ($ltrs as $ltr)
			self::$pieceValues[strtoupper($ltr)] = -self::$pieceValues[$ltr];
		self::$promBonus = ['T' => 4, 't' => -4];
	}

}
