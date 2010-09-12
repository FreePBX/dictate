<?php
// De-register Feature Code - Perform Dictation
$fcc = new featurecode('dictate', 'dodictate');
$fcc->delete();
unset($fcc);

// De-register Feature Code - Email dictation to user
$fcc = new featurecode('dictate', 'senddictate');
$fcc->delete();
unset($fcc);
?>
