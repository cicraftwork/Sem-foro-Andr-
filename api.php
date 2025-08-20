<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

// Archivo donde se guardará el estado
$estadoFile = 'estado_catalogo.json';

// Función para leer el estado actual
function leerEstado($archivo) {
    if (!file_exists($archivo)) {
        return [
            'ocupado' => false,
            'usuario' => null,
            'timestamp' => null,
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
    $estado = json_decode($contenido, true);
    
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
    
    return $estado;
}

// Función para guardar el estado
function guardarEstado($archivo, $estado) {
    $estado['ultima_actualizacion'] = date('Y-m-d H:i:s');
    return file_put_contents($archivo, json_encode($estado, JSON_PRETTY_PRINT));
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
                    'timestamp_limite' => date('Y-m-d H:i:s', time() + 30), // 30 segundos como en frontend
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
        $estado = limpiarVisitantesOffline($estado);
        $estado = procesarAsignacionPendiente($estado);
        guardarEstado($estadoFile, $estado);
        
        echo json_encode([
            'success' => true,
            'data' => $estado
        ]);
        break;
        
    case 'POST':
        // Recibir datos del frontend
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            echo json_encode([
                'success' => false,
                'message' => 'Datos inválidos'
            ]);
            break;
        }
        
        $accion = $input['accion'] ?? '';
        $usuario = $input['usuario'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        switch ($accion) {
            case 'heartbeat':
                if (empty($usuario)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Usuario requerido para heartbeat'
                    ]);
                    break;
                }
                
                $estadoActual = leerEstado($estadoFile);
                $estadoActual = limpiarVisitantesOffline($estadoActual);
                $estadoActual = actualizarVisitante($estadoActual, $usuario, $ip);
                $estadoActual = procesarAsignacionPendiente($estadoActual);
                
                if (guardarEstado($estadoFile, $estadoActual)) {
                    echo json_encode([
                        'success' => true,
                        'data' => $estadoActual
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al actualizar heartbeat'
                    ]);
                }
                break;
                
            case 'tomar_control':
                if (empty($usuario)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Usuario requerido'
                    ]);
                    break;
                }
                
                $estadoActual = leerEstado($estadoFile);
                $estadoActual = limpiarVisitantesOffline($estadoActual);
                $estadoActual = actualizarVisitante($estadoActual, $usuario, $ip);
                $estadoActual = procesarAsignacionPendiente($estadoActual);
                
                if ($estadoActual['ocupado'] || $estadoActual['asignacion_pendiente']['activa']) {
                    $mensaje = $estadoActual['ocupado'] 
                        ? 'El catálogo ya está ocupado por ' . $estadoActual['usuario']
                        : 'Hay una asignación pendiente en proceso';
                    
                    echo json_encode([
                        'success' => false,
                        'message' => $mensaje
                    ]);
                } else {
                    $estadoActual['ocupado'] = true;
                    $estadoActual['usuario'] = $usuario;
                    $estadoActual['timestamp'] = date('d/m H:i');
                    
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
                        echo json_encode([
                            'success' => false,
                            'message' => 'Error al guardar el estado'
                        ]);
                    }
                }
                break;
                
            case 'solicitar_uso':
                if (empty($usuario)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Usuario requerido'
                    ]);
                    break;
                }
                
                $estadoActual = leerEstado($estadoFile);
                $estadoActual = limpiarVisitantesOffline($estadoActual);
                $estadoActual = actualizarVisitante($estadoActual, $usuario, $ip);
                $estadoActual = procesarAsignacionPendiente($estadoActual);
                
                if (!$estadoActual['ocupado'] && !$estadoActual['asignacion_pendiente']['activa']) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'El catálogo está libre, puedes tomarlo directamente'
                    ]);
                    break;
                }
                
                // Verificar si ya está en cola
                $yaEnCola = false;
                foreach ($estadoActual['cola_solicitudes'] as $solicitud) {
                    if ($solicitud['usuario'] === $usuario) {
                        $yaEnCola = true;
                        break;
                    }
                }
                
                if ($yaEnCola) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Ya estás en la cola de solicitudes'
                    ]);
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
                            'notificar_a' => $estadoActual['usuario'] // Para notificar al usuario actual
                        ]);
                    } else {
                        echo json_encode([
                            'success' => false,
                            'message' => 'Error al procesar solicitud'
                        ]);
                    }
                }
                break;
                
            case 'cancelar_solicitud':
                if (empty($usuario)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Usuario requerido'
                    ]);
                    break;
                }
                
                $estadoActual = leerEstado($estadoFile);
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
                        echo json_encode([
                            'success' => false,
                            'message' => 'Error al cancelar solicitud'
                        ]);
                    }
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'No estás en la cola de solicitudes'
                    ]);
                }
                break;
                
            case 'confirmar_asignacion':
                if (empty($usuario)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Usuario requerido'
                    ]);
                    break;
                }
                
                $estadoActual = leerEstado($estadoFile);
                $estadoActual = limpiarVisitantesOffline($estadoActual);
                $estadoActual = actualizarVisitante($estadoActual, $usuario, $ip);
                
                if ($estadoActual['asignacion_pendiente']['activa'] && 
                    $estadoActual['asignacion_pendiente']['usuario'] === $usuario) {
                    
                    // Tomar control
                    $estadoActual['ocupado'] = true;
                    $estadoActual['usuario'] = $usuario;
                    $estadoActual['timestamp'] = date('d/m H:i');
                    
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
                        echo json_encode([
                            'success' => false,
                            'message' => 'Error al confirmar asignación'
                        ]);
                    }
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'No tienes una asignación pendiente válida'
                    ]);
                }
                break;
                
            case 'rechazar_asignacion':
                if (empty($usuario)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Usuario requerido'
                    ]);
                    break;
                }
                
                $estadoActual = leerEstado($estadoFile);
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
                            'timestamp_limite' => date('Y-m-d H:i:s', time() + 30),
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
                        echo json_encode([
                            'success' => false,
                            'message' => 'Error al rechazar asignación'
                        ]);
                    }
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'No tienes una asignación pendiente válida'
                    ]);
                }
                break;
                
            case 'liberar_control':
                $estadoActual = leerEstado($estadoFile);
                $estadoActual = limpiarVisitantesOffline($estadoActual);
                $estadoActual = procesarAsignacionPendiente($estadoActual);
                
                if (!$estadoActual['ocupado']) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'El catálogo ya está libre'
                    ]);
                } else {
                    $usuarioAnterior = $estadoActual['usuario'];
                    $colaPendiente = $estadoActual['cola_solicitudes'];
                    
                    $estadoActual['ocupado'] = false;
                    $estadoActual['usuario'] = null;
                    $estadoActual['timestamp'] = null;
                    
                    // Activar asignación pendiente si hay cola
                    if (count($estadoActual['cola_solicitudes']) > 0) {
                        $siguiente = $estadoActual['cola_solicitudes'][0];
                        $estadoActual['asignacion_pendiente'] = [
                            'usuario' => $siguiente['usuario'],
                            'timestamp_limite' => date('Y-m-d H:i:s', time() + 30), // 30 segundos
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
                        echo json_encode([
                            'success' => false,
                            'message' => 'Error al liberar el catálogo'
                        ]);
                    }
                }
                break;
                
            default:
                echo json_encode([
                    'success' => false,
                    'message' => 'Acción no válida: ' . $accion
                ]);
        }
        break;
        
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Método no permitido'
        ]);
}
?>