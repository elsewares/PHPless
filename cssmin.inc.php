<?php
// Remove the string literals from a set of code; put them back in with replace_strings()
function remove_strings($str) {
	$strings = array();
	$string_id = 0;
	$i = 0;
	
	while ($i < strlen($str)) {
		$pos_single = strpos($str, "'", $i);
		if ($pos_single === FALSE) $pos_single = strlen($str);
		
		$pos_double = strpos($str, '"', $i);
		if ($pos_double === FALSE) $pos_double = strlen($str);
		
		$pos_comment = strpos($str, "//", $i);
		if ($pos_comment === FALSE) $pos_comment = strlen($str);
		
		$pos_multicomment = strpos($str, "/*", $i);
		if ($pos_multicomment === FALSE) $pos_multicomment = strlen($str);
		
		//echo "Starting at $i: single $pos_single, double $pos_double, regex $pos_regex, comment $pos_comment, multiline = $pos_multicomment<br />\n";
		if ($pos_comment < $pos_single && $pos_comment < $pos_double && $pos_comment <= $pos_multicomment) {
			//echo "Find single comment: ";
			$tmp = strpos($str, "\n", $pos_comment+2);
			$i = ($tmp === FALSE)? strlen($str) : $tmp+1;
			//echo "Moved to $i \"".substr($str, $i-15, 15)."##POS##".substr($str, $i, 15)."\"<br />\n";
			next;
		} elseif ($pos_multicomment < $pos_single && $pos_multicomment < $pos_double) {
			//echo "Find multi-line comment: ";
			$tmp = strpos($str, "*/", $pos_multicomment+2);
			$i = ($tmp === FALSE)? strlen($str) : $tmp+2;
			//echo "Moved to $i \"".substr($str, $i-15, 15)."##POS##".substr($str, $i, 15)."\"<br />\n";
			next;
		} elseif ($pos_single < $pos_double) {
			//echo "Find single-quoted string: ";
			$start = $pos_single;
			$end = $start;
			$j = $start+1;
			while ($j <= strlen($str)) {
				if ($str[$j] === "'") {
					// This is a closing character; but is it escaped?
					if ($str[$j-1] === '\\') {
						// Character is escaped, but is the escape character escaped?
						if ($str[$j-2] === '\\') {
							// String ends with a literal '\'; this is the end of the string
							$end = $j;
							break;
						} else {
							// This string terminator is escaped; keep on going
						}
					} else {
						// Character is not escaped; this is the end of the string
						$end = $j;
						break;
					}
				}
				$j++;
			}	
			if ($end == $start) {
				// Unterminated string found!
				return false;
			}
			// Remove the string
			$strings[$string_id] = substr($str, $start+1, $end-$start-1); // Save string
			$str = substr($str, 0, $start+1)."???".$string_id."???".substr($str, $end); // Replace with ID
			//echo "$string_id ($start): ".htmlspecialchars(substr($str, $start-15, 55))."<br />\n";
			$i = $pos_single+7+strlen($string_id); // Jump over ID and closing string character
			$string_id++; // Advance ID
		} elseif ($pos_double < strlen($str)) {
			//echo "Find double-quoted string: ";
			$start = $pos_double;
			$end = $start;
			$j = $start+1;
			while ($j <= strlen($str)) {
				if ($str[$j] === '"') {
					// This is a closing character; but is it escaped?
					if ($str[$j-1] === '\\') {
						// Character is escaped, but is the escape character escaped?
						if ($str[$j-2] === '\\') {
							// String ends with a literal '\'; this is the end of the string
							$end = $j;
							break;
						} else {
							// This string terminator is escaped; keep on going
						}
					} else {
						// Character is not escaped; this is the end of the string
						$end = $j;
						break;
					}
				}
				$j++;
			}	
			if ($end == $start) {
				// Unterminated string found!
				return false;
			}
			// Remove the string
			$strings[$string_id] = substr($str, $start+1, $end-$start-1); // Save string
			$str = substr($str, 0, $start+1)."???".$string_id."???".substr($str, $end); // Replace with ID
			//echo "$string_id ($start): ".htmlspecialchars(substr($str, $start-15, 55))."<br />\n";
			$i = $pos_double+7+strlen($string_id); // Jump over ID and closing string character
			$string_id++; // Advance ID
		} else {
			$i = strlen($str);
		}
		$i++;
	}
	//echo htmlspecialchars($str);
	return array($str, $strings);
}

// Replace the strings from an array back into the code; reverses remove_strings()
function replace_strings($str, $strings) {
	if (count($strings) < 1) return $str; // No changes needed
	foreach($strings as $id => $string) {
		$tag = "???".$id."???";
		$str = str_replace($tag, $string, $str);
	}
	return $str;
}


// shrink a CSS file
function cssMin($str, $verbose = false) {
	$original = $str;
	$str = str_replace("\r", "\n", $str); // Newlines into line feeds
	$str = preg_replace("/\/\/.*?\n/", "\n", $str); // Remove single-line comments
	$str = preg_replace("/\/\*.*\*\//msU", " ", $str); // Remove multi-line comments

	list($str, $strings) = remove_strings($str); // Protect string literals in the code
	
	$str = preg_replace("/\s+\n/", "\n", $str); // Remove whitespace at end of line
	$str = preg_replace("/\n\s+/", "\n", $str); // Remove whitespace at beginning of line
	$str = preg_replace("/\n+/m", "\n", $str); // Replace multiple line breaks with one
	$str = preg_replace("/ +/", ' ', $str); // Replace multiple spaces with one

	$str = preg_replace("/ *([\{\};]) */", "$1", $str); // Remove whitespace after terminators
	$str = preg_replace("/{([^\n])/", "{\n$1", $str); // Ensure opening braces are on their own line
	$str = preg_replace("/([\{\;])([^\n])/", "$1\n$2", $str); // Ensure closing braces and semicolons end the line
	$str = preg_replace("/\s*:\s*/", ":", $str); // Remove whitespace around colons
	$str = preg_replace("/\n{\n/", "{\n", $str); // Jump opening braces up to their owner's line, if on their own
	$str = preg_replace("/([^\n])}/", "$1\n}", $str); // Jump closing braces to their own line
	
	$str = replace_strings($str, $strings); // Replace string literals
	
	if ($verbose) {
		$start = strlen($original);
		$end = strlen($str);
		$ratio = sprintf("%.2f", (1-$end/$start)*100);
		$str = "/* Original = $start bytes, End = $end bytes ($ratio% savings) */\n" . $str;
	}
	return $str;
}