<?php
// Health check / test service
header('Content-Type: application/json');
echo json_encode(["service"=>"customer-service", "status"=>"ok"]);
