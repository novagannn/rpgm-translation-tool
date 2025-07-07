<?php
header("Content-Type: application/json");

$rulesFile = "rules.json";
if (file_exists($rulesFile)) {
    $content = file_get_contents($rulesFile);
    echo $content;
} else {
    echo json_encode([]);
}