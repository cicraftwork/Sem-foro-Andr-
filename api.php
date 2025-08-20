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
            'ultima_actualizacion' => date('Y-m-d H:i:s')
        ];
    }
    
    $contenido = file_get_contents($archivo);
    return json_decode($contenido, true);
}

// Función para guardar el estado
function guardarEstado($archivo, $estado) {
    $estado['ultima_actualizacion'] = date('Y-m-d H:i:s');
    return file_put_contents($archivo, json_encode($estado, JSON_PRETTY_PRINT));
}

// Obtener método HTTP
$metodo = $_SERVER['REQUEST_METHOD'];

switch ($metodo) {
    case 'GET':
        // Devolver estado actual
        $estado = leerEstado($estadoFile);
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
        
        switch ($accion) {
            case 'tomar_control':
                $usuario = $input['usuario'] ?? '';
                if (empty($usuario)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Usuario requerido'
                    ]);
                    break;
                }
                
                $estadoActual = leerEstado($estadoFile);
                
                if ($estadoActual['ocupado']) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'El catálogo ya está ocupado por ' . $estadoActual['usuario']
                    ]);
                } else {
                    $nuevoEstado = [
                        'ocupado' => true,
                        'usuario' => $usuario,
                        'timestamp' => date('d/m H:i')
                    ];
                    
                    if (guardarEstado($estadoFile, $nuevoEstado)) {
                        echo json_encode([
                            'success' => true,
                            'message' => $usuario . ' ha tomado control del catálogo',
                            'data' => $nuevoEstado
                        ]);
                    } else {
                        echo json_encode([
                            'success' => false,
                            'message' => 'Error al guardar el estado'
                        ]);
                    }
                }
                break;
                
            case 'liberar_control':
                $estadoActual = leerEstado($estadoFile);
                
                if (!$estadoActual['ocupado']) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'El catálogo ya está libre'
                    ]);
                } else {
                    $usuarioAnterior = $estadoActual['usuario'];
                    $nuevoEstado = [
                        'ocupado' => false,
                        'usuario' => null,
                        'timestamp' => null
                    ];
                    
                    if (guardarEstado($estadoFile, $nuevoEstado)) {
                        echo json_encode([
                            'success' => true,
                            'message' => $usuarioAnterior . ' ha liberado el catálogo',
                            'data' => $nuevoEstado
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
                    'message' => 'Acción no válida'
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