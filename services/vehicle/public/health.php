<?php
// Health check / test service
header('Content-Type: application/json');
echo json_encode(["service"=>"Vehicle-service", "status"=>"ok"]);
