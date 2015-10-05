<?php
namespace pmimporter;
use pmimporter\Misc;

abstract class Blocks {
	protected static $blockIds = [];
	protected static $blockNames = [];
	public static $trTab = [];

	const INVALID_BLOCK = 248;

	public static function __init() {
		if (count(self::$blockIds)) return; // Only read them once...

		// Read block definitions
		if (defined('CLASSLIB_DIR')) {
			$tab = Misc::readTable(CLASSLIB_DIR."pmimporter/blocks.txt");
		} else {
			$tab = Misc::readTable(dirname(realpath(__FILE__))."/blocks.txt");
		}
		if ($tab === null) die("Unable to read blocks.txt\n");

		for($i=0;$i<256;++$i) {
			self::$trTab[chr($i)] = chr(INVALID_BLOCK);
		}

		foreach ($tab as $ln)	 {
			$code = array_shift($ln);
			$name = array_shift($ln);
			$acode = $code < 0 ? -$code : $code;
			self::$blockNames[$acode] = $name;
			self::$blockIds[$name] = $acode;
			$chr = chr($acode);
			if ($code >= 0) {
				if (isset(self::$trTab[$chr])) unset(self::$trTab[$chr]);
			} else {
				if (count($ln)) self::$trTab[$chr] = chr((int)$ln[0]);
			}
		}
	}
	public static function getBlockById($id) {
		if (isset(self::$blockNames[$id])) return self::$blockNames[$id];
		return null;
	}
	public static function getBlockByName($name) {
		if (isset(self::$blockIds[$name])) return self::$blockIds[$name];
		return null;
	}
	public static function addRule($cid,$nid) {
		if ($cid === null || $nid === null) return;
		if ($cid == $nid) return;
		if ($nid < 0) return;
		self::$trTab[chr($cid)] = chr($nid);
	}
}
