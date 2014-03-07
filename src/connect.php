<?php

// Sample Connect Script

include "TableRow.php";

use Brainvial\TableRow;
use Brainvial\U;

TableRow::connectDB(['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'tablerow_tests']);
