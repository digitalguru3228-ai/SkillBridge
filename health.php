<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['status'=>'ok','service'=>'SKILLBRIDGE','timestamp'=>gmdate('c')], JSON_UNESCAPED_SLASHES);
