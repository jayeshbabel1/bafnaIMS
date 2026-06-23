<?php

require __DIR__.'/../vendor/autoload.php';

echo '<pre>';

echo "autoload: ";
var_dump(file_exists(__DIR__.'/../vendor/autoload.php'));

echo "tcpdf file: ";
var_dump(file_exists(__DIR__.'/../vendor/tecnickcom/tcpdf/tcpdf.php'));

echo "class: ";
var_dump(class_exists('TCPDF'));