<?php
// Health check / test service
header('Content-Type: application/json');
echo json_encode(["service"=>"order-service", "status"=>"ok"]);
