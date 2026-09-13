<?php
$_SESSION = [];
// Mock header function so it doesn't actually redirect and die, we can catch it.
// Actually, PHP CLI doesn't stop on header(), but checkRole calls exit;
// We can test it by seeing if the script terminates before printing "SUCCESS".
