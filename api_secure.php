<?php
// api_secure.php - Versión endurecida para producción
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// CORS restrictivo (cambiar por tu dominio)
$allowed_origins = ['https://tudominio.com', 'https://www.tudominio.com'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
}
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Rate limiting simple
function checkRateLimit($ip) {
    $rate_file = "rate_$ip.txt";
    $current_time = time();
    
    if (file_exists($rate_file)) {
        $data = json_decode(file_get_contents($rate_file), true);
        if ($current_time - $data['last_request'] < 1) { // 1 request per second
            if ($data['count'] >= 10) { // Max 10 requests
                http_response_code(429);
                die(json_encode(['success' => false, 'message' => 'Rate limit exceeded']));
            }
            $data['count']++;
        } else {
            $data = ['count' => 1, 'last_request' => $current_time];
        }
    } else {
        $data = ['count' => 1, 'last_request' => $current_time];
    }
    
    file_put_contents($rate_file, json_encode($data));
}

// Validación de usuario whitelist
function validarUsuario($usuario) {
    $usuarios_permitidos = ['André', 'Nicolás', 'Invitado'];
    return in_array($usuario, $usuarios_permitidos);
}

// Sanitización de entrada
function sanitizarInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Log de auditoría
function logAccion($accion, $usuario, $ip, $success) {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'accion' => $accion,
        'usuario' => $usuario,
        'ip' => $ip,
        'success' => $success,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    file_put_contents('audit.log', json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
}

// Verificar método y origen
$metodo = $_SERVER['REQUEST_METHOD'];
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Rate limiting
checkRateLimit($ip);

// Archivo de estado protegido
$estadoFile = 'estado_catalogo.json';

// Funciones existentes con validaciones añadidas...
function leerEstado($archivo) {
    if (!file_exists($archivo)) {
        return [
            'ocupado' => false,
            'usuario' => null,
            'timestamp' => null,
            'inicio_uso' => null,
            'cola_solicitudes' => [],
            'visitantes_online' => [],
            'asignacion_pendiente' => [
                'usuario' => null,
                'timestamp_limite' => null,
                'activa' => false
            ],
            'ultima_actualizacion' => date('Y-m-d H:i:s')
        ];
    }
    
    $contenido = file_get_contents($archivo);
    if ($contenido === false) {
        logAccion('error', 'system', $ip, false);
        return null;
    }
    
    $estado = json_decode($contenido, true);
    if ($estado === null) {
        logAccion('json_corrupted', 'system', $ip, false);
        return null;
    }
    
    // Migración de estructura (mismo código que antes...)
    if (!isset($estado['cola_solicitudes'])) $estado['cola_solicitudes'] = [];
    if (!isset($estado['visitantes_online'])) $estado['visitantes_online'] = [];
    if (!isset($estado['asignacion_pendiente'])) {
        $estado['asignacion_pendiente'] = [
            'usuario' => null,
            'timestamp_limite' => null,
            'activa' => false
        ];
    }
    if (!isset($estado['inicio_uso'])) $estado['inicio_uso'] = null;
    
    return $estado;
}

function guardarEstado($archivo, $estado) {
    $estado['ultima_actualizacion'] = date('Y-m-d H:i:s');
    $json = json_encode($estado, JSON_PRETTY_PRINT);
    
    // Escritura atómica
    $temp_file = $archivo . '.tmp';
    if (file_put_contents($temp_file, $json, LOCK_EX) !== false) {
        return rename($temp_file, $archivo);
    }
    return false;
}

// Resto del código API con validaciones añadidas...
switch ($metodo) {
    case 'GET':
        $estado = leerEstado($estadoFile);
        if ($estado === null) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error interno']);
            exit;
        }
        
        // Limpiar datos sensibles antes de enviar
        unset($estado['visitantes_online']); // No exponer IPs
        
        echo json_encode([
            'success' => true,
            'data' => $estado
        ]);
        logAccion('get_estado', 'anonymous', $ip, true);
        break;
        
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['accion'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
            logAccion('invalid_input', 'unknown', $ip, false);
            exit;
        }
        
        $accion = sanitizarInput($input['accion']);
        $usuario = sanitizarInput($input['usuario'] ?? '');
        
        // Validar usuario
        if (!empty($usuario) && !validarUsuario($usuario)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Usuario no autorizado']);
            logAccion($accion, $usuario, $ip, false);
            exit;
        }
        
        // Procesar acción (código existente con logs añadidos)
        switch ($accion) {
            case 'tomar_control':
                // ... código existente ...
                logAccion('tomar_control', $usuario, $ip, true);
                break;
                
            case 'liberar_control':
                // ... código existente ...
                logAccion('liberar_control', $usuario, $ip, true);
                break;
                
            // ... resto de casos ...
                
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Acción no válida']);
                logAccion('invalid_action', $usuario, $ip, false);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        logAccion('invalid_method', 'unknown', $ip, false);
}
?>