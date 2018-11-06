<?php

define('PATH_VENDOR', __DIR__ . '/../vendor'); // Path for vendor library
define('PATH_LIB', __DIR__ . '/../lib'); // Path for core library
define('PATH_CACHE', __DIR__ . '/../cache'); // Path to cache folder

// Classes
require_once PATH_LIB . '/Exceptions.php';
require_once PATH_LIB . '/Format.php';
require_once PATH_LIB . '/FormatAbstract.php';
require_once PATH_LIB . '/Bridge.php';
require_once PATH_LIB . '/BridgeAbstract.php';
require_once PATH_LIB . '/FeedExpander.php';
require_once PATH_LIB . '/Cache.php';
require_once PATH_LIB . '/Authentication.php';
require_once PATH_LIB . '/Configuration.php';
require_once PATH_LIB . '/BridgeCard.php';
require_once PATH_LIB . '/BridgeList.php';
require_once PATH_LIB . '/ParameterValidator.php';

// Functions
require_once PATH_LIB . '/html.php';
require_once PATH_LIB . '/error.php';
require_once PATH_LIB . '/contents.php';

// Vendor
require_once PATH_VENDOR . '/simplehtmldom/simple_html_dom.php';
require_once PATH_VENDOR . '/php-urljoin/src/urljoin.php';
