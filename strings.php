<?php
function strsnip($str, $len = 20) {
	if (strlen($str) < $len) {
		return $str;
	}
	return substr($str, 0, $len-3) . "...";
}

function startsWith($haystack, $needle)
{
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}

function endsWith($haystack, $needle)
{
    $length = strlen($needle);
    $start  = $length * -1; //negative
    return (substr($haystack, $start) === $needle);
}
?>