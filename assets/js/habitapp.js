async function sugerirCredenciales() {
    let nombre = document.getElementById('nombre').value.trim().toLowerCase();
    let paterno = document.getElementById('paterno').value.trim().toLowerCase();
    let ci = document.getElementById('ci').value.trim();

    if (nombre.length > 0 && paterno.length > 0) {
        // Primera letra del nombre concatenada con el apellido paterno sin espacios
        let usuarioGen = nombre.charAt(0) + paterno.replace(/\s+/g, '');
        document.getElementById('usuario').value = usuarioGen;
        verificarUsuario(usuarioGen); // Validar automáticamente en tiempo real
    }
    if (ci.length > 0) {
        document.getElementById('password').value = ci;
    }
}

async function verificarUsuario(usuario) {
    let feedback = document.getElementById('usuario_feedback');
    if (usuario.length === 0) {
        feedback.innerHTML = "";
        return;
    }
    try {
        let response = await fetch('check_username.php?u=' + encodeURIComponent(usuario));
        let result = await response.json();
        if (result.existe) {
            let html = "<span class='text-danger fw-bold mb-1 d-block'>⚠️ Este usuario ya existe. Sugerencias:</span>";
            if (result.sugerencias) {
                result.sugerencias.forEach(sug => {
                    html += `<button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 me-1 fw-bold" onclick="usarSugerencia('${sug}')">${sug}</button>`;
                });
            }
            feedback.innerHTML = html;
        } else {
            feedback.innerHTML = "<span class='text-success fw-bold'>✅ Nombre de usuario disponible.</span>";
        }
    } catch (error) {
        console.error("Error al validar el usuario", error);
    }
}

function usarSugerencia(sug) {
    document.getElementById('usuario').value = sug;
    verificarUsuario(sug); // Vuelve a validar para mostrar el mensaje de éxito (verde)
}