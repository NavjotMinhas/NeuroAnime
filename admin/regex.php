<?php
$subject = "abcdef";
$pattern = '/(?<=http:\/\/)www\..*?(?=\/)/';
preg_match($pattern, 'http://www.megavideo.com/v/E4UJ3QGN6f7abfd2838a88bc84fc971cb87ef7dc', $matches);
print_r($matches);
?>