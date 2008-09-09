<?php

// Register Feature Code - Perform Dictation
$performdictate = _("Perform dictation");
$fcc = new featurecode('dictate', 'dodictate');
$fcc->setDescription($performdictate);
$fcc->setDefault('*34');
$fcc->update();
unset($fcc);

// Email dictation to user
$emaildictation = _("Email completed dictation");
$fcc = new featurecode('dictate', 'senddictate');
$fcc->setDescription($emaildictation);
$fcc->setDefault('*35');
$fcc->update();
unset($fcc);


?>
