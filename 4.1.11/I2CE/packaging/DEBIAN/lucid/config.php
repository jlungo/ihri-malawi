<?php

$depends[] = 'php-pear';
$recommends[] = 'php5-tidy';


$scripts['postinst'] = '#!/bin/sh
pear install -s text_password console_getopt
';


$scripts['links'] =  
    "/usr/lib/iHRIS/lib/4.1/I2CE/tools/apache_tail.php usr/bin/apache_tail\n";
;