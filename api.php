<?php
// Security headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// CORS restrictivo - CAMBIAR POR TU DOMINIO
$allowed_origins = [
    'https://tudominio.com',
    'https://www.tudominio.com',
    'http://localhost:3000',  // Para desarrollo
    'http://127.0.0.1:3000'   // Para desarrollo
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_HOST'] ?? '';
if (in_array($origin, $allowed_origins) || strpos($origin, 'localhost') !== false) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header('Access-Control-Allow-Origin: *'); // Fallback temporal - CAMBIAR EN PRODUCCIÓN
}

header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

// Rate limiting básico
function checkRateLimit() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rate_file = __DIR__ . "/rate_$ip.tmp";
    $now = time();
    
    if (file_exists($rate_file)) {
        $data = json_decode(file_get_contents($rate_file), true);
        if ($data && $now - $data['time'] < 60) {
            if ($data['count'] >= 100) { // 100 requests per minute
                http_response_code(429);
                die(json_encode(['success' => false, 'message' => 'Rate limit exceeded']));
            }
            $data['count']++;
        } else {
            $data = ['time' => $now, 'count' => 1];
        }
    } else {
        $data = ['time' => $now, 'count' => 1];
    }
    
    file_put_contents($rate_file, json_encode($data), LOCK_EX);
}

// Input validation
function validateUser($usuario) {
    $allowed = ['André', 'Nicolás', 'Invitado'];
    return in_array($usuario, $allowed, true);
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Apply rate limiting
checkRateLimit();

// Archivo donde se guardará el estado
$estadoFile = __DIR__ . '/estado_catalogo.json';

// Función para leer el estado actual
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
    if ($contenido === false) return null;
    
    $estado = json_decode($contenido, true);
    if ($estado === null) return null;
    
    // Migrar estructura antigua si es necesario
    if (!isset($estado['cola_solicitudes'])) {
        $estado['cola_solicitudes'] = [];
    }
    if (!isset($estado['visitantes_online'])) {
        $estado['visitantes_online'] = [];
    }
    if (!isset($estado['asignacion_pendiente'])) {
        $estado['asignacion_pendiente'] = [
            'usuario' => null,
            'timestamp_limite' => null,
            'activa' => false
        ];
    }
    if (!isset($estado['inicio_uso'])) {
        $estado['inicio_uso'] = null;
    }
    
    return $estado;
}

// Función para guardar el estado
function guardarEstado($archivo, $estado) {
    $estado['ultima_actualizacion'] = date('Y-m-d H:i:s');
    
    // Escritura atómica
    $temp = $archivo . '.tmp';
    $result = file_put_contents($temp, json_encode($estado, JSON_PRETTY_PRINT), LOCK_EX);
    if ($result !== false) {
        return rename($temp, $archivo);
    }
    return false;
}

// Función para limpiar visitantes offline y solicitudes huérfanas
function limpiarVisitantesOffline($estado) {
    $tiempoLimite = time() - 60; // 60 segundos sin heartbeat = offline
    $usuariosOffline = [];
    
    foreach ($estado['visitantes_online'] as $index => $visitante) {
        if (isset($visitante['ultimo_heartbeat']) && $visitante['ultimo_heartbeat'] < $tiempoLimite) {
            $usuariosOffline[] = $visitante['usuario'];
            unset($estado['visitantes_online'][$index]);
        }
    }
    
    // Remover solicitudes de usuarios offline
    foreach ($usuariosOffline as $usuarioOffline) {
        foreach ($estado['cola_solicitudes'] as $i => $solicitud) {
            if ($solicitud['usuario'] === $usuarioOffline) {
                unset($estado['cola_solicitudes'][$i]);
            }
        }
    }
    
    // Reindexar arrays y actualizar posiciones
    $estado['visitantes_online'] = array_values($estado['visitantes_online']);
    $estado['cola_solicitudes'] = array_values($estado['cola_solicitudes']);
    
    // Actualizar posiciones en la cola
    for ($i = 0; $i < count($estado['cola_solicitudes']); $i++) {
        $estado['cola_solicitudes'][$i]['posicion'] = $i + 1;
    }
    
    return $estado;
}

// Función para procesar asignación pendiente y timeouts
function procesarAsignacionPendiente($estado) {
    if ($estado['asignacion_pendiente']['activa']) {
        $ahora = time();
        $limite = strtotime($estado['asignacion_pendiente']['timestamp_limite']);
        
        if ($ahora > $limite) {
            // Timeout, pasar al siguiente o liberar
            $usuarioTimeout = $estado['asignacion_pendiente']['usuario'];
            
            // Remover de cola al usuario que hizo timeout
            foreach ($estado['cola_solicitudes'] as $i => $solicitud) {
                if ($solicitud['usuario'] === $usuarioTimeout) {
                    unset($estado['cola_solicitudes'][$i]);
                    break;
                }
            }
            $estado['cola_solicitudes'] = array_values($estado['cola_solicitudes']);
            
            // Asignar al siguiente o liberar
            if (count($estado['cola_solicitudes']) > 0) {
                $siguiente = $estado['cola_solicitudes'][0];
                $estado['asignacion_pendiente'] = [
                    'usuario' => $siguiente['usuario'],
                    'timestamp_limite' => date('Y-m-d H:i:s', time() + 120), // 2 minutos
                    'activa' => true
                ];
            } else {
                $estado['asignacion_pendiente'] = [
                    'usuario' => null,
                    'timestamp_limite' => null,
                    'activa' => false
                ];
            }
        }
    }
    
    return $estado;
}

// Función para actualizar visitante online
function actualizarVisitante($estado, $usuario, $ip = null) {
    $encontrado = false;
    
    foreach ($estado['visitantes_online'] as &$visitante) {
        if ($visitante['usuario'] === $usuario) {
            $visitante['ultimo_heartbeat'] = time();
            if ($ip) $visitante['ip'] = $ip;
            $encontrado = true;
            break;
        }
    }
    
    if (!$encontrado) {
        $estado['visitantes_online'][] = [
            'usuario' => $usuario,
            'ultimo_heartbeat' => time(),
            'timestamp_conexion' => date('d/m H:i'),
            'ip' => $ip ?: 'unknown'
        ];
    }
    
    return $estado;
}

// Obtener método HTTP
$metodo = $_SERVER['REQUEST_METHOD'];

switch ($metodo) {
    case 'GET':
        // Devolver estado actual
        $estado = leerEstado($estadoFile);
        if ($estado === null) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error interno']);
            exit;
        }
        
        $estado = limpiarVisitantesOffline($estado);
        $estado = procesarAsignacionPendiente($estado);
        
        // Calcular tiempo de uso si está ocupado
        if ($estado['ocupado'] && $estado['inicio_uso']) {
            $estado['tiempo_uso_minutos'] = floor((time() - $estado['inicio_uso']) / 60);
        }
        
        // Remover datos sensibles
        $estadoPublico = $estado;
        foreach ($estadoPublico['visitantes_online'] as &$visitante) {
            unset($visitante['ip']); // No exponer IPs
        }
        
        guardarEstado($estadoFile, $estado);
        
        echo json_encode([
            'success' => true,
            'data' => $estadoPublico
        ]);
        break;
        
    case 'POST':
        // Recibir datos del frontend
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['accion'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
            exit;
        }
        
        $accion = sanitizeInput($input['accion']);
        $usuario = sanitizeInput($input['usuario'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // Validar usuario si se proporciona
        if (!empty($usuario) && !validateUser($usuario)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Usuario no autorizado']);
            exit;
        }
        
        switch ($accion) {
            case 'heartbeat':
                if (empty($usuario)) {
                    echo json_encode(['success' => false, 'message' => 'Usuario requerido para heartbeat']);
                    break;
                }
                
                $estadoActual = leerEstado($estadoFile);
                if ($estadoActual === null) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Error interno']);
                    break;
                }
                
                $estadoActual = limpiarVisitantesOffline($estadoActual);
                $estadoActual = actualizarVisitante($estadoActual, $usuario, $ip);
                $estadoActual = procesarAsignacionPendiente($estadoActual);
                
                if (guardarEstado($estadoFile, $estadoActual)) {
                    echo json_encode(['success' => true, 'data' => $estadoActual]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al actualizar heartbeat']);
                }
                break;
                
            case 'tomar_control':
                if (empty($usuario)) {
                    echo json_encode(['success' => false, 'message' => 'Usuario requerido']);
                    break;
                }
                
                $estadoActual = leerEstado($estadoFile);
                if ($estadoActual === null) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Error interno']);
                    break;
                }
                
                $estadoActual = limpiarVisitantesOffline($estadoActual);
                $estadoActual = actualizarVisitante($estadoActual, $usuario, $ip);
                $estadoActual = procesarAsignacionPendiente($estadoActual);
                
                if ($estadoActual['ocupado'] || $estadoActual['asignacion_pendiente']['activa']) {
                    // Catálogo ocupado - entrar automáticamente en cola
                    $yaEnCola = false;
                    foreach ($estadoActual['cola_solicitudes'] as $solicitud) {
                        if ($solicitud['usuario'] === $usuario) {
                            $yaEnCola = true;
                            break;
                        }
                    }
                    
                    if ($yaEnCola) {
                        echo json_encode(['success' => false, 'message' => 'Ya estás en la cola de solicitudes']);
                    } else {
                        $posicion = count($estadoActual['cola_solicitudes']) + 1;
                        
                        $estadoActual['cola_solicitudes'][] = [
                            'usuario' => $usuario,
                            'timestamp' => date('Y-m-d H:i:s'),
                            'hora' => date('H:i'),
                            'posicion' => $posicion
                        ];
                        
                        if (guardarEstado($estadoFile, $estadoActual)) {
                            echo json_encode([
                                'success' => true,
                                'message' => $usuario . ' ha entrado en la cola (posición ' . $posicion . ')',
                                'data' => $estadoActual,
                                'notificar_a' => $estadoActual['usuario'],
                                'entrada_cola' => true
                            ]);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Error al procesar solicitud']);
                        }
                    }
                } else {
                    // Catálogo libre - tomar control directo
                    $estadoActual['ocupado'] = true;
                    $estadoActual['usuario'] = $usuario;
                    $estadoActual['timestamp'] = date('d/m H:i');
                    $estadoActual['inicio_uso'] = time();
                    
                    // Limpiar cola de solicitudes y asignación pendiente
                    $estadoActual['cola_solicitudes'] = [];
                    $estadoActual['asignacion_pendiente'] = [
                        'usuario' => null,
                        'timestamp_limite' => null,
                        'activa' => false
                    ];
                    
                    if (guardarEstado($estadoFile, $estadoActual)) {
                        echo json_encode([
                            'success' => true,
                            'message' => $usuario . ' ha tomado control del catálogo',
                            'data' => $estadoActual
                        ]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Error al guardar el estado']);
                    }
                }
                break;
                
            case 'cancelar_solicitud':
                if (empty($usuario)) {
                    echo json_encode(['success' => false, 'message' => 'Usuario requerido']);
                    break;
                }
                
                $estadoActual = leerEstado($estadoFile);
                if ($estadoActual === null) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Error interno']);
                    break;
                }
                
                $estadoActual = limpiarVisitantesOffline($estadoActual);
                $estadoActual = actualizarVisitante($estadoActual, $usuario, $ip);
                
                $encontrado = false;
                foreach ($estadoActual['cola_solicitudes'] as $i => $solicitud) {
                    if ($solicitud['usuario'] === $usuario) {
                        unset($estadoActual['cola_solicitudes'][$i]);
                        $encontrado = true;
                        break;
                    }
                }
                
                if ($encontrado) {
                    $estadoActual['cola_solicitudes'] = array_values($estadoActual['cola_solicitudes']);
                    
                    // Actualizar posiciones
                    for ($i = 0; $i < count($estadoActual['cola_solicitudes']); $i++) {
                        $estadoActual['cola_solicitudes'][$i]['posicion'] = $i + 1;
                    }
                    
                    if (guardarEstado($estadoFile, $estadoActual)) {
                        echo json_encode([
                            'success' => true,
                            'message' => $usuario . ' ha salido de la cola',
                            'data' => $estadoActual
                        ]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Error al cancelar solicitud']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'No estás en la cola de solicitudes']);
                }
                break;
                
            case 'confirmar_asignacion':
                if (empty($usuario)) {
                    echo json_encode(['success' => false, 'message' => 'Usuario requerido']);
                    break;
                }
                
                $estadoActual = leerEstado($estadoFile);
                if ($estadoActual === null) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Error interno']);
                    break;
                }
                
                $estadoActual = limpiarVisitantesOffline($estadoActual);
                $estadoActual = actualizarVisitante($estadoActual, $usuario, $ip);
                
                if ($estadoActual['asignacion_pendiente']['activa'] && 
                    $estadoActual['asignacion_pendiente']['usuario'] === $usuario) {
                    
                    // Tomar control
                    $estadoActual['ocupado'] = true;
                    $estadoActual['usuario'] = $usuario;
                    $estadoActual['timestamp'] = date('d/m H:i');
                    $estadoActual['inicio_uso'] = time();
                    
                    // Limpiar asignación y remover de cola
                    $estadoActual['asignacion_pendiente'] = [
                        'usuario' => null,
                        'timestamp_limite' => null,
                        'activa' => false
                    ];
                    
                    // Remover al usuario de la cola
                    foreach ($estadoActual['cola_solicitudes'] as $i => $solicitud) {
                        if ($solicitud['usuario'] === $usuario) {
                            unset($estadoActual['cola_solicitudes'][$i]);
                            break;
                        }
                    }
                    $estadoActual['cola_solicitudes'] = array_values($estadoActual['cola_solicitudes']);
                    
                    // Actualizar posiciones
                    for ($i = 0; $i < count($estadoActual['cola_solicitudes']); $i++) {
                        $estadoActual['cola_solicitudes'][$i]['posicion'] = $i + 1;
                    }
                    
                    if (guardarEstado($estadoFile, $estadoActual)) {
                        echo json_encode([
                            'success' => true,
                            'message' => $usuario . ' ha confirmado y tomado control del catálogo',
                            'data' => $estadoActual
                        ]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Error al confirmar asignación']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'No tienes una asignación pendiente válida']);
                }
                break;
                
            case 'rechazar_asignacion':
                if (empty($usuario)) {
                    echo json_encode(['success' => false, 'message' => 'Usuario requerido']);
                    break;
                }
                
                $estadoActual = leerEstado($estadoFile);
                if ($estadoActual === null) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Error interno']);
                    break;
                }
                
                $estadoActual = limpiarVisitantesOffline($estadoActual);
                $estadoActual = actualizarVisitante($estadoActual, $usuario, $ip);
                
                if ($estadoActual['asignacion_pendiente']['activa'] && 
                    $estadoActual['asignacion_pendiente']['usuario'] === $usuario) {
                    
                    // Remover al usuario de la cola
                    foreach ($estadoActual['cola_solicitudes'] as $i => $solicitud) {
                        if ($solicitud['usuario'] === $usuario) {
                            unset($estadoActual['cola_solicitudes'][$i]);
                            break;
                        }
                    }
                    $estadoActual['cola_solicitudes'] = array_values($estadoActual['cola_solicitudes']);
                    
                    // Asignar al siguiente o liberar
                    if (count($estadoActual['cola_solicitudes']) > 0) {
                        $siguiente = $estadoActual['cola_solicitudes'][0];
                        $estadoActual['asignacion_pendiente'] = [
                            'usuario' => $siguiente['usuario'],
                            'timestamp_limite' => date('Y-m-d H:i:s', time() + 120), // 2 minutos
                            'activa' => true
                        ];
                        
                        // Actualizar posiciones
                        for ($i = 0; $i < count($estadoActual['cola_solicitudes']); $i++) {
                            $estadoActual['cola_solicitudes'][$i]['posicion'] = $i + 1;
                        }
                    } else {
                        $estadoActual['asignacion_pendiente'] = [
                            'usuario' => null,
                            'timestamp_limite' => null,
                            'activa' => false
                        ];
                    }
                    
                    if (guardarEstado($estadoFile, $estadoActual)) {
                        echo json_encode([
                            'success' => true,
                            'message' => $usuario . ' ha rechazado la asignación',
                            'data' => $estadoActual
                        ]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Error al rechazar asignación']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'No tienes una asignación pendiente válida']);
                }
                break;
                
            case 'liberar_control':
                $estadoActual = leerEstado($estadoFile);
                if ($estadoActual === null) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Error interno']);
                    break;
                }
                
                $estadoActual = limpiarVisitantesOffline($estadoActual);
                $estadoActual = procesarAsignacionPendiente($estadoActual);
                
                if (!$estadoActual['ocupado']) {
                    echo json_encode(['success' => false, 'message' => 'El catálogo ya está libre']);
                } else {
                    $usuarioAnterior = $estadoActual['usuario'];
                    $colaPendiente = $estadoActual['cola_solicitudes'];
                    
                    $estadoActual['ocupado'] = false;
                    $estadoActual['usuario'] = null;
                    $estadoActual['timestamp'] = null;
                    $estadoActual['inicio_uso'] = null;
                    
                    // Activar asignación pendiente si hay cola
                    if (count($estadoActual['cola_solicitudes']) > 0) {
                        $siguiente = $estadoActual['cola_solicitudes'][0];
                        $estadoActual['asignacion_pendiente'] = [
                            'usuario' => $siguiente['usuario'],
                            'timestamp_limite' => date('Y-m-d H:i:s', time() + 120), // 2 minutos
                            'activa' => true
                        ];
                    }
                    
                    if (guardarEstado($estadoFile, $estadoActual)) {
                        echo json_encode([
                            'success' => true,
                            'message' => $usuarioAnterior . ' ha liberado el catálogo',
                            'data' => $estadoActual,
                            'cola_pendiente' => $colaPendiente
                        ]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Error al liberar el catálogo']);
                    }
                }
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Acción no válida: ' . $accion]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>