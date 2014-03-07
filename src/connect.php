<?php

// Sample Connect Script

include "U.php";
include "TableRow.php";

use Brainvial\TableRow;
use Brainvial\U;

TableRow::connectDB(['host' => 'localhost', 'user' => 'root', 'pass' => '', 'db' => 'tablerow_tests']);
