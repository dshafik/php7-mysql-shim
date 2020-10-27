<?php
$xml = simplexml_load_file("build/coverage/xml/mysql.php.xml");
echo "COVERAGE=" . $xml->file->totals->lines->attributes()->percent;