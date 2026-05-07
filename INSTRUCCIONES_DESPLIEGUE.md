# 🚀 Guía de Despliegue en Producción - HabitApp

Este documento detalla los pasos exactos para instalar y poner en marcha el sistema **HabitApp** en un servidor de producción (hosting compartido, VPS o servidor dedicado, preferentemente basado en Linux).

---

## 📋 1. Requisitos del Servidor
Asegúrese de que su servidor cumpla con las siguientes especificaciones mínimas:
*   **Servidor Web:** Apache o Nginx.
*   **PHP:** Versión 8.0 o superior.
*   **Base de Datos:** MySQL 5.7+ o MariaDB 10.4+.
*   **Extensiones PHP requeridas:**
    *   `mysqli` (Para la conexión a base de datos)
    *   `gd` (VITAL para la generación de PDFs con imágenes/logos mediante DomPDF)
    *   `mbstring` (Para el manejo correcto de caracteres especiales)
    *   `fileinfo` (Requerido por DomPDF)

---

## 📂 2. Carga de Archivos
1. Comprima todos los archivos del proyecto en un archivo `.zip`.
2. Suba el archivo a la carpeta pública de su servidor web (usualmente `public_html`, `www` o `/var/www/html/` en Linux) mediante cPanel, FTP (FileZilla) o SSH.
3. Descomprima el archivo.

---

## 🗄️ 3. Base de Datos
1. Ingrese a su gestor de bases de datos en producción (ej. phpMyAdmin o consola MySQL).
2. Cree una nueva base de datos vacía (ej. `nombreusuario_habitapp`).
3. Seleccione la base de datos y vaya a la pestaña **Importar**.
4. Suba el archivo que se encuentra en la ruta: `bdd/habitapp.sql`.
5. Verifique que todas las tablas (`reservas`, `pagos`, `habitacion`, `funcionario`, etc.) se hayan creado correctamente.

---

## ⚙️ 4. Configuración del Sistema
Debe conectar el código con la nueva base de datos en producción.

**1. Editar `conexion.php`:**
Abra el archivo en la raíz del proyecto y modifique las credenciales por las de su servidor de producción:
```php
$host = "localhost"; // Normalmente es localhost, o la IP de su DB
$usuario = "su_usuario_de_bd";
$contrasena = "su_contraseña_segura";
$base_datos = "nombreusuario_habitapp";
```

**2. Editar `config.php` (Opcional):**
Si el hotel cambia de nombre, número de teléfono, WhatsApp o NIT, simplemente edite las constantes definidas en este archivo. Estos datos se reflejarán automáticamente en todos los PDFs del sistema.

---

## 🔐 5. Permisos de Carpetas (Exclusivo para Linux)
En entornos Linux, el servidor web (usualmente el usuario `www-data`) necesita permisos de escritura explícitos para poder guardar archivos o imágenes.

1. Navegue a la carpeta del proyecto a través de la terminal o su gestor de archivos.
2. Localice la carpeta `uploads/ci/` (si no existe, créela).
3. Otórguele permisos de escritura (`775` o `777`). En consola SSH, el comando sería:
   ```bash
   chmod -R 777 uploads/
   ```
   *Nota: Si esta carpeta no tiene permisos, el sistema fallará al intentar subir la foto del Carnet durante el Check-in.*

---

## 🖼️ 6. Solución de Problemas Comunes (Troubleshooting)

**Error:** El PDF de arqueo tira un error fatal mencionando `The PHP GD extension is required`.
**Solución:** Debe activar la extensión GD en su panel de control de Hosting (sección "Seleccionar versión de PHP" o "Extensiones PHP") marcando la casilla `gd`. Si tiene acceso a la terminal, instálela con `sudo apt-get install php-gd` y reinicie Apache (`sudo systemctl restart apache2`).

**Error:** Se ven errores y advertencias de PHP en la pantalla.
**Solución:** En producción, es imperativo apagar la visualización de errores por seguridad. En su archivo `php.ini` o panel de hosting, asegúrese de que la directiva `display_errors` esté en `Off`.

---

**🎉 ¡Listo! Su sistema HabitApp ya debería estar funcionando perfectamente en la nube.**
Acceda mediante su dominio (ej. `www.suhotel.com`) e inicie sesión con su usuario SuperAdmin.