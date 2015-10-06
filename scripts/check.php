<?php
if (!defined('CLASSLIB_DIR'))
	require_once(dirname(realpath(__FILE__)).'/../classlib/autoload.php');

require_once(CLASSLIB_DIR."common.php");
use pmimporter\LevelFormatManager;
use pmimporter\Blocks;

define('CMD',array_shift($argv));
// Handle options
$minx = $minz = $maxx = $maxz = null;
$chkchunks = false;
while (count($argv) > 0) {
	if (substr($argv[0],0,2) != "--") break;
	$opt = array_shift($argv);
	if (($val = preg_replace('/^--min-x=/','',$opt)) != $opt) {
		$minx = (int)$val;
	} elseif (($val = preg_replace('/^--max-x=/','',$opt)) != $opt) {
		$maxx = (int)$val;
	} elseif (($val = preg_replace('/^--min-z=/','',$opt)) != $opt) {
		$minz = (int)$val;
	} elseif (($val = preg_replace('/^--max-z=/','',$opt)) != $opt) {
		$maxz = (int)$val;
	} elseif (($val = preg_replace('/^--x=/','',$opt)) != $opt) {
		$minx = $maxx = (int)$val;
	} elseif (($val = preg_replace('/^--z=/','',$opt)) != $opt) {
		$minz = $maxz = (int)$val;
	} elseif ($opt == "--check-chunks") {
		$chkchunks = true;
	} elseif ($opt == "--no-check-chunks") {
		$chkchunks = false;
	} else {
		die("Invalid option: $opt\n");
	}
}


$wpath=array_shift($argv);
if (!isset($wpath)) die("No path specified\n");
if (!file_exists($wpath)) die("$wpath: does not exist\n");

$fmt = LevelFormatManager::getFormat($wpath);
if ($fmt === null) die("$wpath: unrecognized format\n");
$level  = new $fmt($wpath);
echo "FORMAT:    ".$level::getFormatName()."\n";
echo "SEED:      ".$level->getSeed()."\n";
echo "Generator: ".$level->getGenerator()."\n";
echo "Presets:   ".$level->getPresets()."\n";
$spawn = $level->getSpawn();
echo "Spawn:     ".implode(',',[$spawn->getX(),$spawn->getY(),$spawn->getZ()])."\n";

$chunks = $level->getChunks();
echo "Chunks:    ".count($chunks)."\n";

$qminx = $qmaxx = $qminz = $qmaxz = null;
foreach ($chunks as $chunk) {
	list($cx,$cz) = $chunk;

	if ($qminx === null || $cx < $qminx) $qminx = $cx;
	if ($qmaxx === null || $cx > $qmaxx) $qmaxx = $cx;
	if ($qminz === null || $cz < $qminz) $qminz = $cz;
	if ($qmaxz === null || $cz > $qmaxz) $qmaxz = $cz;
}
echo "Chunk area: ($qminx,$qminz)-($qmaxx,$qmaxz)\n";
if (!$chkchunks) exit(0);
$cnt = 0;
function incr(&$stats,$attr) {
	if (isset($stats[$attr])) {
		++$stats[$attr];
	} else {
		$stats[$attr] = 1;
	}
}
$stats = [];
foreach ($chunks as $chunk) {
	list($cx,$cz) = $chunk;
	if ( ($minx !== null && $cx < $minx) || ($maxx !== null && $cx > $maxx) ||
			 ($minz !== null && $cz < $minz) || ($maxz !== null && $cz > $maxz) ) continue;
  //echo "($cx,$cz)\n";
	++$cnt;
	$chunk = $level->getChunk($cx,$cz);
	if ($chunk->isPopulated()) incr($stats,"-populated");
	if ($chunk->isGenerated()) incr($stats,"-generated");
	if ($chunk->isLightPopulated()) incr($stats,"-lighted");
	$blocks = $chunk->getRawBlocks();
	if (!is_array($blocks)) $blocks = [$blocks];
	foreach ($blocks as $str) {
		for ($i=0,$l = strlen($str);$i<$l;$i++) {
			incr($stats,ord($str{$i}));
		}
	}
	$heightMap = $chunk->getHeightMap();
	foreach ($heightMap as $h) {
		if (!isset($stats["Height:Max"])) {
			$stats["Height:Max"] = $h;
		} elseif ($h > $stats["Height:Max"]) {
			$stats["Height:Max"] = $h;
		}
		if (!isset($stats["Height:Min"])) {
			$stats["Height:Min"] = $h;
		} elseif ($h < $stats["Height:Min"]) {
			$stats["Height:Min"] = $h;
		}
		if (!isset($stats["Height:Sum"])) {
			$stats["Height:Sum"] = $h;
		} else {
			$stats["Height:Sum"] += $h;
		}
		incr($stats,"Height:Count");
	}

}
echo "Number of chunks selected: ".$cnt."\n";
if (isset($stats["Height:Count"]) && isset($stats["Height:Sum"])) {
	$stats["Height:Avg"] = $stats["Height:Sum"]/$stats["Height:Count"];
	unset($stats["Height:Count"]);
	unset($stats["Height:Sum"]);
}

$sorted = array_keys($stats);
natsort($sorted);
foreach ($sorted as $k) {
	if (is_numeric($k)) {
		$v = Blocks::getBlockById($k);
		$v = $v !== null ? "$v ($k)" : "*Unsupported* ($k)";
	} else {
		$v = $k;
	}
	echo "  $v:\t".$stats[$k]."\n";
}

/*
	foreach ($chunk->getEntities() as $entity) {
		if (!isset($entity->id)) continue;
		if ($entity->id->getValue() == "Item") {
			incr($stats,"ENTITY:Item:".$entity->Item->id->getValue());
			continue;
		}
		incr($stats,"ENTITY:".$entity->id->getValue());

	}
	foreach ($chunk->getTileEntities() as $tile) {
		if (!isset($tile->id)) continue;
		incr($stats,"TILE:".$tile->id->getValue());
	}
}

*/
