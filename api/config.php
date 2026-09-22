<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');
if ($_SERVER['REQUEST_METHOD']==='OPTIONS') exit;
$host=getenv('DB_HOST') ?: 'localhost';
$db=getenv('DB_NAME') ?: 'reservas_stage';
$user=getenv('DB_USER') ?: 'root';
$pass=getenv('DB_PASS') ?: '';
$dsn="mysql:host={$host};dbname={$db};charset=utf8mb4";
try{$pdo=new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);}
catch(Throwable $e){http_response_code(500);echo json_encode(['error'=>'No se pudo conectar a la base de datos']);exit;}
function body(): array {return json_decode(file_get_contents('php://input'),true) ?: [];}
function respond($data,int $status=200): never {http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE);exit;}
