# Dolce Home Talleres — v1.6.0

Plugin de gestión de talleres de mantas XXL para Dolce Home.

## Changelog

### v1.6.0
**Features:**
1. **Editar inscripción — Taller y Materiales:** El modal de edición ahora permite cambiar el taller asignado y todos los materiales (color, tipo de lana, micras, medida). Al cambiar de taller o turno, los cupos se gestionan automáticamente.
2. **Reenviar email:** Nuevo botón 📧 en la lista de alumnos inscriptos. Antes de enviar, muestra un diálogo de confirmación con el email destino.
3. **Gráfico Seña / Total / Cortesía:** Segundo gráfico de dona en la sección Alumnos Inscriptos mostrando la distribución de inscripciones activas por tipo de pago.
4. **Papelera de reciclaje:** Nueva página en el menú (🗑️ Papelera) con:
   - Talleres eliminados (usa papelera nativa de WordPress) — restaurar o eliminar definitivamente.
   - Alumnos inscriptos eliminados (soft delete con campo `deleted_at`) — restaurar o eliminar definitivamente.

**Bug fix:**
- **Error "Datos inválidos" en modal de edición:** Corregido bug en JavaScript donde `$.extend({action,nonce}, form.serialize())` mezclaba objeto con string, enviando parámetros mal formados. La fix usa serialización pura: `form.serialize() + '&action=...&nonce=...'`.

### v1.5.0
- Gráfico de inscripciones por estado (donut)
- Filtro por estado clickeable
- Export CSV informes
- Registro manual mejorado

### v1.4.0
- Editar / confirmar / cancelar inscripciones con sync WC
- Cambiar turno y tipo de pago
- Stat boxes clickeables
- Eliminar y duplicar taller desde dashboard

## Instalación
1. Subir el archivo ZIP en WordPress → Plugins → Agregar → Subir plugin
2. Activar el plugin
3. Asegurarse de tener WooCommerce activo
4. La migración de base de datos se ejecuta automáticamente

## Requisitos
- WordPress 6.0+
- WooCommerce 7.0+
- PHP 7.4+
