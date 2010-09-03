<?php

// Get CSS input
if (PHP_SAPI == 'cli') {
	$css_file = $argv[1];
} else {
	if (empty($_REQUEST['file'])) exit("No input specified");
	$css_file = $_REQUEST['file'];
}
$css_contents = file_get_contents($css_file) or exit("Failed to input CSS file");

// Minimize CSS
require_once('cssmin.inc.php');
$css_contents = cssMin($css_contents);

// Parse CSS, LESS-style
$source = explode("\n", $css_contents);
$variables = array();
$css = array();

$i = 0;
$curElement = array();
$curLevel = 0;

while ($i < count($source)) {
	//echo "$i < ".count($source)."\n";
	$line = &$source[$i];
	if ($line == '') {
		// Empty line
	} elseif (substr($line, 0, 7) == '@import') {
		// Import the listed file
		$file = substr($line, 9, -2); // don't capture leading quotes and trailing quotes and semicolon
		$pos = strpos($file, '.');
		if ($pos === FALSE) $file .= '.less';
		$imported_file = @file_get_contents($file);
		if (!empty($imported_file)) {
			$imported_file = cssMin($css_contents);
			$source = array_slice($source, 0, $i-1).explode("\n", $imported_file).array_slice($source, $i+1);
		}
	} elseif (substr($line,0,1) == '@') {
		// Variable definition
	} elseif (substr($line, -1) == '{') {
		// Defines the beginning of a group
		$name = substr($line, 0, -1); // Trim trailing brace
		$curElement[] = $name;
	} elseif (substr($line, -1) == '}') {
		// Ends the group
		array_pop($curElement);
	} else {
		// This is a property definition
		$pos = strpos($line, ':');
		if ($pos !== FALSE) {
			$name = substr($line, 0, $pos);
			$value = substr($line, $pos+1);
			if (substr($value, -1) == ';') $value = substr($value, 0, -1);
			
			$parts = explode(" ", $value); // Get parts of the value
			foreach($parts as &$part) { // Clean up the parts
				if (intval($part)."" == $part) {
					// Numeric entry
				} elseif (substr($part, 0, 1) == '#') {
					// Color as Hex value
					if (strlen($part) == 4) {
						// 3-digit hex
						$r = hexdec(substr($part,1,1).substr($part,1,1));
						$g = hexdec(substr($part,2,1).substr($part,2,1));
						$b = hexdec(substr($part,3,1).substr($part,3,1));
					} else {
						// 6-digit hex
						$r = hexdec(substr($part,1,2));
						$g = hexdec(substr($part,3,2));
						$b = hexdec(substr($part,5,2));
					}
					$part = "rgb($r,$g,$b)";
				}		
			}
			unset($part); // Break link
			
			$j = 1;
			while ($j < count($parts)) {
				$part = $parts[$j];
				if ($part == '+' || $part == '-') {
					// Add or subtract the next element to the prior
					if (substr($parts[$j-1],0,3) == 'rgb') {
						// Color mode
						$pos = strpos($parts[$j-1], '(');
						list($r,$g,$b,$a) = explode(',', substr($parts[$j-1],$pos+1, -1));
						if (substr($parts[$j+1],0,3) == 'rgb') {
							// Adding color to color
							$pos = strpos($parts[$j+1], '(');
							list($nr, $ng, $nb, $na) = explode(',', substr($parts[$j+1],$pos+1, -1));
							if ($part == '+') {
								$r += $nr;
								$g += $ng;
								$b += $nb;
								$a += $na;
								if ($r > 255) $r = 255;
								if ($g > 255) $g = 255;
								if ($b > 255) $b = 255;
								if ($a > 255) $a = 255;
							} elseif ($part == '-') {
								$r -= $nr;
								$g -= $ng;
								$b -= $nb;
								$a -= $na;
								if ($r < 0) $r = 0;
								if ($g < 0) $g = 0;
								if ($b < 0) $b = 0;
								if ($a < 0) $a = 0;
							}
							$value = ($a == NULL)? "rgb($r,$g,$b)" : "rgba($r,$g,$b,$a)";
							$parts[$j-1] = $value; // Replace original with result
							$parts = array_merge(array_slice($parts,0,$j), array_slice($parts,$j+2)); // Cut out operand and next item;
							$j--; // Jump pointer back one
						} else {
							// Adding integer to color
							if ($part == '+') {
								$r += $parts[$j+1];
								$g += $parts[$j+1];
								$b += $parts[$j+1];
								if ($a != NULL) $a += $parts[$j+1];
								if ($r > 255) $r = 255;
								if ($g > 255) $g = 255;
								if ($b > 255) $b = 255;
								if ($a > 255) $a = 255;
							} elseif ($part == '-') {
								$r -= $parts[$j+1];
								$g -= $parts[$j+1];
								$b -= $parts[$j+1];
								if ($a != NULL) $a -= $parts[$j+1];
								if ($r < 0) $r = 0;
								if ($g < 0) $g = 0;
								if ($b < 0) $b = 0;
								if ($a < 0) $a = 0;
							}
							$value = ($a == NULL)? "rgb($r,$g,$b)" : "rgba($r,$g,$b,$a)";
							$parts[$j-1] = $value; // Replace original with result
							$parts = array_merge(array_slice($parts,0,$j), array_slice($parts,$j+2)); // Cut out operand and next item;
							$j--; // Jump pointer back one
						}
					} else {
						// Numeric mode
						$num = intval($parts[$j-1]).""; // Intval will take the trailing 'px', 'pt', 'em', or '%' off the end. Then concat it with an empty string to keep it a string...
						$unit = substr($parts[$j-1], strlen($num)); // ...so we can use strlen on it
						$new_num = intval($parts[$j+1]);
						if ($part == '+') {
							$num += $new_num;
						} elseif ($part == '-') {
							$num -= $new_num;
						}
						$parts[$j-1] = $num.$unit; // Replace original with result
						$parts = array_merge(array_slice($parts,0,$j), array_slice($parts,$j+2)); // Cut out operand and next item;
						$j--; // Jump pointer back one
					}
				} elseif ($part == '*' || $part == '/') {
					// Multiply or divide the next element from the prior
					$new_num = intval($parts[$j+1]); // Following element must be an integer
					if (substr($parts[$j-1],0,3) == 'rgb') {
						// Color mode
						$pos = strpos($parts[$j-1], '(');
						list($r,$g,$b,$a) = explode(',', substr($parts[$j-1],$pos+1, -1));
						if ($part == '*') {
							$r = $r*$new_num;
							$g = $g*$new_num;
							$b = $b*$new_num;
							if ($a != NULL) $a = $a*$new_num;
							if ($r > 255) $r = 255;
							if ($g > 255) $g = 255;
							if ($b > 255) $b = 255;
							if ($a > 255) $a = 255;
						} elseif ($part == '/') {
							$r = $r/$new_num;
							$g = $g/$new_num;
							$b = $b/$new_num;
							if ($a != NULL) $a = $a/$new_num;
						}
						$value = ($a == NULL)? "rgb($r,$g,$b)" : "rgba($r,$g,$b,$a)";
						$parts[$j-1] = $value; // Replace original with result
						$parts = array_merge(array_slice($parts,0,$j), array_slice($parts,$j+2)); // Cut out operand and next item;
						$j--; // Jump pointer back one
					} else {
						// Numeric mode
						$num = intval($parts[$j-1]).""; // Intval will take the trailing unit label off the end. Then concat it with an empty string to keep it a string...
						$unit = substr($parts[$j-1], strlen($num)); // ...so we can use strlen on it.
						if ($part == '*') {
							$num = $num*$new_num;
						} elseif ($part == '/') {
							$num = $num/$new_num;
						}
						$parts[$j-1] = $num.$unit; // Replace original with result
						$parts = array_merge(array_slice($parts,0,$j), array_slice($parts,$j+2)); // Cut out operand and next item;
						$j--; // Jump pointer back one
					}
				}
				$j++; // Move to next part
			}
			$value = implode(" ", $parts);
			
			$str = implode(" ", $curElement);
			$css[$str][$name] = $value; // Save this property
		}
	}

	$i++; // Move to next line
}

// Build final out of $css
$final = "";
foreach($css as $element => $properties) {
	$final .= "$element {";
	ksort($properties); // Alphabetize properties
	foreach ($properties as $name => $value) {
		$final .= "$name:$value;";
	}
	$final .= "}\n";
}
echo $final;

//print_r($css);