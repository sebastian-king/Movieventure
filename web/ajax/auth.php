<?php
require("../template/top.php");

header('Content-Type: application/json');
echo json_encode(array('auth_status' => auth() ? true : false));
