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
function calcularCambio(id) {
    let totalCobrar = parseFloat(document.getElementById('total_pagar_' + id).value) || 0;
    let inputRecibido = document.getElementById('recibido_' + id);
    let inputCambio = document.getElementById('cambio_' + id);
    
    if (!inputCambio || !inputRecibido) return; // Salir si no estamos en la vista de Check-in
    
    let recibido = parseFloat(inputRecibido.value) || 0;
    let cambio = recibido - totalCobrar;
    
    if (cambio >= 0) {
        inputCambio.value = cambio.toFixed(2);
        inputCambio.classList.replace('text-danger', 'text-success');
    } else {
        inputCambio.value = '0.00';
        inputCambio.classList.replace('text-success', 'text-danger');
    }
}

function toggleServicio(id, precioServicio, noches, checkbox) {
    let totalInput = document.getElementById('total_pagar_' + id);
    let displayTotal = document.getElementById('display_total_' + id);
    let inputRecibido = document.getElementById('recibido_' + id);
    
    let total = parseFloat(totalInput.value) || 0;
    let diferencia = precioServicio * noches;
    
    if (checkbox.checked) { total += diferencia; } else { total -= diferencia; }
    
    totalInput.value = total.toFixed(2);
    displayTotal.innerHTML = 'Bs. ' + total.toFixed(2);
    
    // Actualizar el minimo aceptado en efectivo
    if(inputRecibido) inputRecibido.min = total.toFixed(2);
    
    calcularCambio(id);
}

function actualizarCantidadServicio(id, precioServicio, noches, input) {
    let totalInput = document.getElementById('total_pagar_' + id);
    let displayTotal = document.getElementById('display_total_' + id);
    let inputRecibido = document.getElementById('recibido_' + id);
    
    let total = parseFloat(totalInput.value) || 0;
    let oldValue = parseInt(input.getAttribute('data-old-value')) || 0;
    let newValue = parseInt(input.value) || 0;
    
    let diferencia = (newValue - oldValue) * precioServicio * noches;
    total += diferencia;
    input.setAttribute('data-old-value', newValue);
    
    totalInput.value = total.toFixed(2);
    displayTotal.innerHTML = 'Bs. ' + total.toFixed(2);
    if(inputRecibido) inputRecibido.min = total.toFixed(2);
    calcularCambio(id);
}

function calcularCambioExt(id) {
    let totalCobrar = parseFloat(document.getElementById('monto_cobrar_' + id).value) || 0;
    let inputRecibido = document.getElementById('recibido_ext_' + id);
    let inputCambio = document.getElementById('cambio_ext_' + id);
    if (!inputCambio || !inputRecibido) return;
    
    let recibido = parseFloat(inputRecibido.value) || 0;
    let cambio = recibido - totalCobrar;
    if (cambio >= 0) {
        inputCambio.value = cambio.toFixed(2);
        inputCambio.classList.replace('text-danger', 'text-success');
    } else {
        inputCambio.value = '0.00';
        inputCambio.classList.replace('text-success', 'text-danger');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Listener para calcular costo de extensión de estadía
    document.querySelectorAll('.input-extender').forEach(function(input) {
        input.addEventListener('change', function() {
            let id = this.getAttribute('data-id');
            let oldDate = new Date(this.getAttribute('data-old-date'));
            let newDate = new Date(this.value);
            let tarifaDiaria = parseFloat(document.getElementById('tarifa_diaria_' + id).value) || 0;
            
            let diffTime = Math.abs(newDate - oldDate);
            let diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            let montoCobrar = diffDays * tarifaDiaria;
            
            document.getElementById('monto_cobrar_' + id).value = montoCobrar.toFixed(2);
            document.getElementById('display_extra_' + id).innerText = 'Bs. ' + montoCobrar.toFixed(2);
            document.getElementById('recibido_ext_' + id).min = montoCobrar.toFixed(2);
            calcularCambioExt(id);
        });
    });
});

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
            var tablaReservas = $('#tablaReservas').DataTable({
                "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
                "order": [], // Respetar el orden exacto y oficial enviado por PHP (SQL)
                "columnDefs": [{ "orderable": false, "targets": 7 }],
                "deferRender": true, // Optimización extrema para manejar miles de filas sin lentitud
                "dom": "<'row mb-3'<'col-md-6 d-flex align-items-center'B><'col-md-6'f>>" +
                       "<'row'<'col-sm-12'tr>>" +
                       "<'row mt-3'<'col-md-5'i><'col-md-7'p>>",
                "buttons": [{
                    extend: 'excelHtml5',
                    text: '<i class="lni lni-empty-file"></i> Exportar a Excel',
                    className: 'btn btn-success btn-sm fw-bold shadow-sm',
                    title: 'Reporte_Reservas_HabitApp',
                    exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] } // Excluye la columna 7 (Acciones)
                }]
            });

            // Funcionalidad de Pestañas Interactivas (Sin recargar la página)
            $('.tab-filtro').on('click', function(e) {
                e.preventDefault();
                
                // Cambiar estilos visuales
                $('.tab-filtro').removeClass('active shadow-sm fw-bold').addClass('bg-white border text-dark');
                $(this).removeClass('bg-white border text-dark').addClass('active shadow-sm fw-bold');

                // Aplicar Filtro en la Columna 6 ("Estado") usando Regex para coincidencias exactas
                var estado = $(this).data('estado');
                if (estado === '') {
                    tablaReservas.column(6).search('').draw();
                } else {
                    tablaReservas.column(6).search('^' + estado + '$', true, false).draw();
                }
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

    // 5. Temporizador (Countdown) para Reservas Pendientes (12 horas)
    if (document.getElementById('tablaReservas')) {
        setInterval(function() {
            document.querySelectorAll('.countdown-timer').forEach(function(el) {
                let diff = parseInt(el.getAttribute('data-remaining'));
                
                if (diff <= 0) {
                    el.innerHTML = '<i class="lni lni-timer"></i> Expirado';
                    el.classList.replace('text-danger', 'text-muted');
                } else {
                    let h = Math.floor(diff / 3600);
                    let m = Math.floor((diff % 3600) / 60);
                    let s = diff % 60;
                    h = String(h).padStart(2, '0');
                    m = String(m).padStart(2, '0');
                    s = String(s).padStart(2, '0');
                    
                    let span = el.querySelector('span');
                    if (span) span.innerText = h + ':' + m + ':' + s;
                    
                    el.setAttribute('data-remaining', diff - 1);
                }
            });
        }, 1000);
    }

    // 6. Inicializar Tooltips de Bootstrap (Para el mapa de habitaciones)
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) { return new bootstrap.Tooltip(tooltipTriggerEl); });
    }
});