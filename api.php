<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Catálogo - André, Nicolás e Invitado</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 600px;
            width: 100%;
        }
        
        h1 {
            color: #333;
            margin-bottom: 30px;
            font-size: 2.2em;
        }
        
        .semaforo {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            margin: 20px auto;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 80px;
            border: 8px solid #333;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .libre {
            background: #2ecc71;
            animation: pulse-green 2s infinite;
        }
        
        .ocupado {
            background: #e74c3c;
            animation: pulse-red 2s infinite;
        }
        
        @keyframes pulse-green {
            0% { box-shadow: 0 0 0 0 rgba(46, 204, 113, 0.7); }
            70% { box-shadow: 0 0 0 20px rgba(46, 204, 113, 0); }
            100% { box-shadow: 0 0 0 0 rgba(46, 204, 113, 0); }
        }
        
        @keyframes pulse-red {
            0% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.7); }
            70% { box-shadow: 0 0 0 20px rgba(231, 76, 60, 0); }
            100% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0); }
        }
        
        .estado-texto {
            font-size: 1.5em;
            margin: 20px 0;
            font-weight: bold;
            min-height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .libre-texto {
            color: #2ecc71;
        }
        
        .ocupado-texto {
            color: #e74c3c;
        }
        
        .botones {
            margin-top: 30px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .boton {
            padding: 15px 30px;
            font-size: 1.1em;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
            text-transform: uppercase;
            position: relative;
        }
        
        .boton-andre {
            background: #3498db;
            color: white;
        }
        
        .boton-andre:hover:not(:disabled) {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .boton-nicolas {
            background: #9b59b6;
            color: white;
        }
        
        .boton-nicolas:hover:not(:disabled) {
            background: #8e44ad;
            transform: translateY(-2px);
        }
        
        .boton-invitado {
            background: #f39c12;
            color: white;
        }
        
        .boton-invitado:hover:not(:disabled) {
            background: #e67e22;
            transform: translateY(-2px);
        }
        
        .boton-liberar {
            background: #2ecc71;
            color: white;
        }
        
        .boton-liberar:hover:not(:disabled) {
            background: #27ae60;
            transform: translateY(-2px);
        }
        
        .boton-cancelar {
            background: #e74c3c;
            color: white;
            font-size: 0.9em;
        }
        
        .boton-cancelar:hover:not(:disabled) {
            background: #c0392b;
            transform: translateY(-2px);
        }
        
        .boton:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            transform: none;
        }
        
        .boton.solicitud-pendiente {
            background: #f39c12;
            animation: pulse-orange 1.5s infinite;
        }
        
        @keyframes pulse-orange {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        .info-usuario {
            background: #ecf0f1;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            font-size: 1.1em;
        }
        
        .solicitudes-panel {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
            display: none;
        }
        
        .solicitudes-panel h3 {
            margin: 0 0 15px 0;
            color: #856404;
            font-size: 1.2em;
        }
        
        .solicitud-item {
            background: white;
            padding: 10px;
            border-radius: 5px;
            margin: 5px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid #ffc107;
        }
        
        .timestamp {
            color: #7f8c8d;
            font-size: 0.9em;
            margin-top: 10px;
        }
        
        .status-conexion {
            position: fixed;
            top: 10px;
            right: 10px;
            padding: 8px 12px;
            border-radius: 5px;
            color: white;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .conectado {
            background: #2ecc71;
        }
        
        .desconectado {
            background: #e74c3c;
        }
        
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .notificacion-permiso {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 12px;
            margin: 15px 0;
            color: #0c5460;
            font-size: 0.9em;
        }
        
        .btn-permitir-notificaciones {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 8px;
        }
        
        .btn-permitir-notificaciones:hover {
            background: #138496;
        }
    </style>
</head>
<body>
    <div class="status-conexion" id="status-conexion">🔄 Conectando...</div>
    
    <div class="container" id="container">
        <h1>🗂️ Control de Catálogo</h1>
        
        <div id="notificacion-permiso" class="notificacion-permiso" style="display: none;">
            🔔 <strong>Habilita las notificaciones</strong> para recibir alertas cuando alguien solicite el catálogo.
            <br>
            <button class="btn-permitir-notificaciones" onclick="solicitarPermisoNotificaciones()">
                Permitir Notificaciones
            </button>
        </div>
        
        <div id="semaforo" class="semaforo libre">
            🟢
        </div>
        
        <div id="estado-texto" class="estado-texto libre-texto">
            CATÁLOGO LIBRE
        </div>
        
        <div class="info-usuario">
            <div id="info-actual">Ningún usuario conectado</div>
            <div id="timestamp" class="timestamp"></div>
        </div>
        
        <div id="solicitudes-panel" class="solicitudes-panel">
            <h3>📋 Solicitudes Pendientes</h3>
            <div id="lista-solicitudes"></div>
        </div>
        
        <div class="botones">
            <button id="btn-andre" class="boton boton-andre" onclick="accionUsuario('André')">
                👨‍💼 André Toma Control
            </button>
            
            <button id="btn-nicolas" class="boton boton-nicolas" onclick="accionUsuario('Nicolás')">
                👨‍💻 Nicolás Toma Control
            </button>
            
            <button id="btn-invitado" class="boton boton-invitado" onclick="accionUsuario('Invitado')">
                👤 Invitado Toma Control
            </button>
            
            <button id="btn-liberar" class="boton boton-liberar" onclick="liberarControl()" style="display: none;">
                🔓 Liberar Catálogo
            </button>
        </div>
        
        <div style="margin-top: 30px; color: #7f8c8d; font-size: 0.9em;">
            💡 <strong>Instrucciones:</strong><br>
            • Si está libre: Clic en tu botón para tomar control<br>
            • Si está ocupado: Clic para solicitar uso<br>
            • Libera cuando termines para permitir el acceso
        </div>
    </div>

    <script>
        // URL de la API
        const API_URL = './api.php';
        
        // Estado de conexión y aplicación
        let conectado = false;
        let estadoActual = null;
        let misSolicitudes = new Set(); // Tracking de mis solicitudes
        
        // Verificar y solicitar permisos de notificación al cargar
        function verificarPermisoNotificaciones() {
            if (!("Notification" in window)) {
                console.log("Este navegador no soporta notificaciones");
                return;
            }
            
            if (Notification.permission === "default") {
                document.getElementById('notificacion-permiso').style.display = 'block';
            }
        }
        
        // Solicitar permiso para notificaciones
        function solicitarPermisoNotificaciones() {
            if (!("Notification" in window)) {
                mostrarNotificacion("Tu navegador no soporta notificaciones", "error");
                return;
            }
            
            Notification.requestPermission().then(function (permission) {
                if (permission === "granted") {
                    document.getElementById('notificacion-permiso').style.display = 'none';
                    mostrarNotificacion("¡Notificaciones habilitadas correctamente!", "success");
                    new Notification("Control de Catálogo", {
                        body: "¡Notificaciones habilitadas! Recibirás alertas sobre solicitudes.",
                        icon: "📚"
                    });
                } else {
                    mostrarNotificacion("Notificaciones denegadas. No recibirás alertas.", "warning");
                }
            });
        }
        
        // Mostrar notificación del sistema
        function mostrarNotificacionSistema(titulo, mensaje, icono = "📚") {
            if ("Notification" in window && Notification.permission === "granted") {
                new Notification(titulo, {
                    body: mensaje,
                    icon: icono,
                    requireInteraction: true
                });
            }
        }
        
        // Función para actualizar el indicador de conexión
        function actualizarStatusConexion(status) {
            const statusElement = document.getElementById('status-conexion');
            if (status === 'conectado') {
                statusElement.textContent = '🟢 Conectado';
                statusElement.className = 'status-conexion conectado';
                conectado = true;
            } else {
                statusElement.textContent = '🔴 Desconectado';
                statusElement.className = 'status-conexion desconectado';
                conectado = false;
            }
        }

        // Función para obtener el estado actual del servidor
        async function obtenerEstado() {
            try {
                const response = await fetch(API_URL);
                const result = await response.json();
                
                if (result.success) {
                    const nuevoEstado = result.data;
                    
                    // Detectar cambios importantes para notificaciones
                    if (estadoActual) {
                        detectarCambiosImportantes(estadoActual, nuevoEstado);
                    }
                    
                    estadoActual = nuevoEstado;
                    actualizarDisplay(nuevoEstado);
                    actualizarStatusConexion('conectado');
                } else {
                    console.error('Error al obtener estado:', result.message);
                    actualizarStatusConexion('desconectado');
                }
            } catch (error) {
                console.error('Error de conexión:', error);
                actualizarStatusConexion('desconectado');
            }
        }
        
        // Detectar cambios importantes para notificar
        function detectarCambiosImportantes(estadoAnterior, estadoNuevo) {
            // Notificar cuando se libera el catálogo si tengo solicitudes
            if (estadoAnterior.ocupado && !estadoNuevo.ocupado) {
                const tengeSolicitud = estadoNuevo.solicitudes.some(s => misSolicitudes.has(s.usuario));
                if (tengeSolicitud) {
                    mostrarNotificacionSistema(
                        "🟢 Catálogo Liberado",
                        "¡El catálogo está libre! Puedes tomarlo ahora.",
                        "✅"
                    );
                }
            }
            
            // Notificar nuevas solicitudes si soy el usuario actual
            if (estadoNuevo.ocupado && estadoNuevo.solicitudes.length > estadoAnterior.solicitudes.length) {
                const nuevasSolicitudes = estadoNuevo.solicitudes.filter(s => 
                    !estadoAnterior.solicitudes.some(sa => sa.usuario === s.usuario)
                );
                
                nuevasSolicitudes.forEach(solicitud => {
                    mostrarNotificacionSistema(
                        "📋 Nueva Solicitud",
                        `${solicitud.usuario} solicita usar el catálogo`,
                        "⏰"
                    );
                });
            }
        }

        // Función para enviar acción al servidor
        async function enviarAccion(accion, usuario = null) {
            const container = document.getElementById('container');
            container.classList.add('loading');
            
            try {
                const data = { accion };
                if (usuario) data.usuario = usuario;
                
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    estadoActual = result.data;
                    actualizarDisplay(result.data);
                    mostrarNotificacion(result.message, 'success');
                    actualizarStatusConexion('conectado');
                    
                    // Manejar notificaciones especiales
                    if (result.notificar_usuario && accion === 'solicitar_uso') {
                        // No necesitamos hacer nada especial aquí, la detección automática se encarga
                    }
                    
                    if (result.solicitudes_pendientes && accion === 'liberar_control') {
                        if (result.solicitudes_pendientes.length > 0) {
                            mostrarNotificacionSistema(
                                "📋 Catálogo Liberado",
                                `Hay ${result.solicitudes_pendientes.length} solicitud(es) pendiente(s)`,
                                "⏳"
                            );
                        }
                    }
                    
                } else {
                    mostrarNotificacion(result.message, 'error');
                }
            } catch (error) {
                console.error('Error al enviar acción:', error);
                mostrarNotificacion('Error de conexión con el servidor', 'error');
                actualizarStatusConexion('desconectado');
            } finally {
                container.classList.remove('loading');
            }
        }

        // Función para mostrar notificaciones web (fallback)
        function mostrarNotificacion(mensaje, tipo) {
            // Mostrar también notificación del sistema para eventos importantes
            if (tipo === 'success') {
                mostrarNotificacionSistema("Control de Catálogo", mensaje);
            }
            
            // Crear notificación visual en la página como fallback
            const notificacion = document.createElement('div');
            notificacion.style.cssText = `
                position: fixed;
                top: 20px;
                left: 50%;
                transform: translateX(-50%);
                background: ${tipo === 'success' ? '#2ecc71' : tipo === 'error' ? '#e74c3c' : '#f39c12'};
                color: white;
                padding: 15px 20px;
                border-radius: 8px;
                font-weight: bold;
                z-index: 10000;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                animation: slideDown 0.3s ease;
            `;
            
            const icon = tipo === 'success' ? '✅' : tipo === 'error' ? '❌' : '⚠️';
            notificacion.textContent = `${icon} ${mensaje}`;
            
            document.body.appendChild(notificacion);
            
            setTimeout(() => {
                notificacion.style.animation = 'slideUp 0.3s ease';
                setTimeout(() => document.body.removeChild(notificacion), 300);
            }, 3000);
        }

        // Función para actualizar la interfaz
        function actualizarDisplay(estado) {
            const semaforo = document.getElementById('semaforo');
            const estadoTexto = document.getElementById('estado-texto');
            const infoActual = document.getElementById('info-actual');
            const timestamp = document.getElementById('timestamp');
            const btnAndre = document.getElementById('btn-andre');
            const btnNicolas = document.getElementById('btn-nicolas');
            const btnInvitado = document.getElementById('btn-invitado');
            const btnLiberar = document.getElementById('btn-liberar');
            const solicitudesPanel = document.getElementById('solicitudes-panel');

            if (estado.ocupado) {
                // Estado OCUPADO
                semaforo.className = 'semaforo ocupado';
                semaforo.textContent = '🔴';
                
                estadoTexto.className = 'estado-texto ocupado-texto';
                estadoTexto.textContent = 'CATÁLOGO OCUPADO';
                
                infoActual.textContent = `En uso por: ${estado.usuario}`;
                timestamp.textContent = `Desde: ${estado.timestamp}`;
                
                // Cambiar texto de botones a "solicitar"
                actualizarBotonUsuario(btnAndre, 'André', estado, 'solicitar');
                actualizarBotonUsuario(btnNicolas, 'Nicolás', estado, 'solicitar');
                actualizarBotonUsuario(btnInvitado, 'Invitado', estado, 'solicitar');
                
                btnLiberar.style.display = 'block';
                
            } else {
                // Estado LIBRE
                semaforo.className = 'semaforo libre';
                semaforo.textContent = '🟢';
                
                estadoTexto.className = 'estado-texto libre-texto';
                estadoTexto.textContent = 'CATÁLOGO LIBRE';
                
                infoActual.textContent = 'Ningún usuario conectado';
                timestamp.textContent = 'Disponible para usar';
                
                // Cambiar texto de botones a "tomar control"
                actualizarBotonUsuario(btnAndre, 'André', estado, 'tomar');
                actualizarBotonUsuario(btnNicolas, 'Nicolás', estado, 'tomar');
                actualizarBotonUsuario(btnInvitado, 'Invitado', estado, 'tomar');
                
                btnLiberar.style.display = 'none';
            }
            
            // Mostrar panel de solicitudes si hay alguna
            actualizarPanelSolicitudes(estado.solicitudes || []);
        }
        
        // Actualizar botón de usuario según el estado
        function actualizarBotonUsuario(boton, usuario, estado, modo) {
            const tieneSolicitud = (estado.solicitudes || []).some(s => s.usuario === usuario);
            
            if (modo === 'tomar') {
                boton.textContent = `${getIconoUsuario(usuario)} ${usuario} Toma Control`;
                boton.disabled = false;
                boton.className = `boton boton-${usuario.toLowerCase()}`;
            } else {
                if (tieneSolicitud) {
                    boton.textContent = `⏳ Cancelar Solicitud (${usuario})`;
                    boton.className = `boton boton-cancelar`;
                    misSolicitudes.add(usuario);
                } else {
                    boton.textContent = `📝 ${usuario} Solicita Uso`;
                    boton.className = `boton boton-${usuario.toLowerCase()}`;
                }
                boton.disabled = false;
            }
        }
        
        // Obtener icono del usuario
        function getIconoUsuario(usuario) {
            switch(usuario) {
                case 'André': return '👨‍💼';
                case 'Nicolás': return '👨‍💻';
                case 'Invitado': return '👤';
                default: return '👤';
            }
        }
        
        // Actualizar panel de solicitudes
        function actualizarPanelSolicitudes(solicitudes) {
            const panel = document.getElementById('solicitudes-panel');
            const lista = document.getElementById('lista-solicitudes');
            
            if (solicitudes.length === 0) {
                panel.style.display = 'none';
                return;
            }
            
            panel.style.display = 'block';
            lista.innerHTML = '';
            
            solicitudes.forEach(solicitud => {
                const item = document.createElement('div');
                item.className = 'solicitud-item';
                item.innerHTML = `
                    <span>${getIconoUsuario(solicitud.usuario)} <strong>${solicitud.usuario}</strong> - ${solicitud.timestamp_display}</span>
                `;
                lista.appendChild(item);
            });
        }

        // Función principal para manejar acciones de usuario
        function accionUsuario(usuario) {
            if (!estadoActual) return;
            
            const tieneSolicitud = (estadoActual.solicitudes || []).some(s => s.usuario === usuario);
            
            if (estadoActual.ocupado) {
                if (tieneSolicitud) {
                    // Cancelar solicitud
                    enviarAccion('cancelar_solicitud', usuario);
                    misSolicitudes.delete(usuario);
                } else {
                    // Solicitar uso
                    enviarAccion('solicitar_uso', usuario);
                }
            } else {
                // Tomar control
                enviarAccion('tomar_control', usuario);
            }
        }

        // Funciones que serán llamadas por los botones
        function liberarControl() {
            enviarAccion('liberar_control');
        }

        // Inicializar la aplicación
        function inicializar() {
            verificarPermisoNotificaciones();
            obtenerEstado();
            
            // Sincronizar cada 2 segundos
            setInterval(obtenerEstado, 2000);
        }

        // Agregar estilos para animaciones
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideDown {
                from { transform: translate(-50%, -100%); opacity: 0; }
                to { transform: translate(-50%, 0); opacity: 1; }
            }
            @keyframes slideUp {
                from { transform: translate(-50%, 0); opacity: 1; }
                to { transform: translate(-50%, -100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);

        // Iniciar cuando la página esté cargada
        document.addEventListener('DOMContentLoaded', inicializar);
    </script>
</body>
</html>