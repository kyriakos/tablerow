<?php

// Sample Connect Script

include "TableRow.php";

use Kyriakos\TableRow;


TableRow::connectDB(['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'tablerow_tests']);
