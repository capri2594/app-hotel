/* =========================================
   Funciones del Dashboard
========================================= */
function showIframe(activeId) {
    document.getElementById('dashboard-home').style.display = 'none';
    document.getElementById('iframe-container').style.display = 'block';
    
    document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
    if (activeId) document.getElementById(activeId).classList.add('active');
}
  
function showDashboardHome() {
    document.getElementById('iframe-container').style.display = 'none';
    document.getElementById('dashboard-home').style.display = 'block';
    
    document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
    document.getElementById('nav-dashboard').classList.add('active');
}

/* =========================================
   Funciones de Recepción (Check-in)
========================================= */
function calcularCambio(id, totalCobrar) {
    let recibido = parseFloat(document.getElementById('recibido_' + id).value) || 0;
    let cambio = recibido - totalCobrar;
    let inputCambio = document.getElementById('cambio_' + id);
    
    if (cambio >= 0) {
        inputCambio.value = cambio.toFixed(2);
        inputCambio.classList.replace('text-danger', 'text-success');
    } else {
        inputCambio.value = '0.00';
        inputCambio.classList.replace('text-success', 'text-danger');
    }
}

/* =========================================
   Inicialización de Plugins (Global)
========================================= */
document.addEventListener('DOMContentLoaded', function() {
    
    // 1. Inicializar DataTables (Si existe la tabla en la vista actual)
    if (typeof $ !== 'undefined' && $.fn.DataTable) {
        
        // Configuracion para Tabla Funcionarios
        if ($('#tablaFuncionarios').length > 0) {
            $('#tablaFuncionarios').DataTable({
                "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
                "columnDefs": [{ "orderable": false, "targets": 7 }] // Ocultar ordenamiento en "Acciones"
            });
        }
        // Configuracion para Tabla Reservas
        if ($('#tablaReservas').length > 0) {
            $('#tablaReservas').DataTable({
                "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
                "order": [[ 0, "desc" ]], // Ordenar por ID descendente
                "columnDefs": [{ "orderable": false, "targets": 7 }]
            });
        }
    }

    // 2. Inicializar Counter Up (Front-end)
    if (typeof counterUp !== 'undefined' && document.querySelector('.countup')) {
        var cu = new counterUp({ start: 0, duration: 2000, intvalues: true, interval: 100, append: " " });
        cu.start();
    }
    
    // 3. Validación dinámica de fechas para Check-in / Check-out (Si existen los inputs)
    const checkin = document.querySelector('input[name="fecha_ingreso"]');
    const checkout = document.querySelector('input[name="fecha_salida"]');
    if (checkin && checkout) {
        checkin.addEventListener('change', function() {
            if (this.value) {
                // Separar la fecha para crearla en la zona horaria local (Evita saltos de 2 días)
                let parts = this.value.split('-');
                let nextDay = new Date(parts[0], parts[1] - 1, parts[2]);
                nextDay.setDate(nextDay.getDate() + 1); // Exactamente 1 día después
                
                let nextDayString = nextDay.getFullYear() + '-' + String(nextDay.getMonth() + 1).padStart(2, '0') + '-' + String(nextDay.getDate()).padStart(2, '0');
                
                checkout.min = nextDayString;
                if (!checkout.value || checkout.value <= this.value) checkout.value = nextDayString;
            }
        });
    }

    // 4. Inicializar Select2 para el código de país (si existe el elemento y la librería)
    if (typeof $ !== 'undefined' && $.fn.select2 && $('.select2-country').length > 0) {
        const paises = [
            { id: '+54', text: 'Argentina', flag: '🇦🇷' },
            { id: '+591', text: 'Bolivia', flag: '🇧🇴' },
            { id: '+55', text: 'Brasil', flag: '🇧🇷' },
            { id: '+56', text: 'Chile', flag: '🇨🇱' },
            { id: '+57', text: 'Colombia', flag: '🇨🇴' },
            { id: '+506', text: 'Costa Rica', flag: '🇨🇷' },
            { id: '+53', text: 'Cuba', flag: '🇨🇺' },
            { id: '+593', text: 'Ecuador', flag: '🇪🇨' },
            { id: '+503', text: 'El Salvador', flag: '🇸🇻' },
            { id: '+34', text: 'España', flag: '🇪🇸' },
            { id: '+1', text: 'EEUU / Canadá', flag: '🇺🇸' },
            { id: '+502', text: 'Guatemala', flag: '🇬🇹' },
            { id: '+504', text: 'Honduras', flag: '🇭🇳' },
            { id: '+52', text: 'México', flag: '🇲🇽' },
            { id: '+505', text: 'Nicaragua', flag: '🇳🇮' },
            { id: '+507', text: 'Panamá', flag: '🇵🇦' },
            { id: '+595', text: 'Paraguay', flag: '🇵🇾' },
            { id: '+51', text: 'Perú', flag: '🇵🇪' },
            { id: '+1787', text: 'Puerto Rico', flag: '🇵🇷' },
            { id: '+1809', text: 'Rep. Dominicana', flag: '🇩🇴' },
            { id: '+598', text: 'Uruguay', flag: '🇺🇾' },
            { id: '+58', text: 'Venezuela', flag: '🇻🇪' }
        ];

        $('.select2-country').select2({
            theme: 'bootstrap-5', // Aplica el estilo de Bootstrap 5
            width: '100%', // Se adapta perfectamente a su contenedor
            data: paises.map(p => ({ id: p.id, text: p.text, flag: p.flag })),
            templateResult: function (idioma) {
                if (!idioma.id) { return idioma.text; }
                return idioma.flag + ' ' + idioma.id + ' <span class="text-muted small ms-1">(' + idioma.text + ')</span>';
            },
            templateSelection: function (idioma) {
                if (!idioma.id) { return idioma.text; }
                return idioma.flag + ' ' + idioma.id;
            },
            escapeMarkup: function(m) { return m; } // Fundamental para que las banderas se impriman
        });

        // Seleccionar Bolivia por defecto
        $('.select2-country').val('+591').trigger('change');
    }
});