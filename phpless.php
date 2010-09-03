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
				if ($part == '+') {
					// Add the next element to the prior
					if (substr($parts[$j-1],0,3) == 'rgb') {
						// Color mode
					} else {
						// Integer mode
					}
				} elseif ($part == '-') {
					// Subtract the next element from the prior
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