<?php

require_once (__DIR__.'/parser.php');

$pageUrl = 'https://imdvor.ru/catalog/gold/';
$parseData = Parser::parsePage($pageUrl);

echo '<pre>';
print_r($parseData);
