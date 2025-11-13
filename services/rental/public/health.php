<?php
// Health check / test service
header('Content-Type: application/json');
echo json_encode(["service"=>"Rental-service", "status"=>"ok"]);
