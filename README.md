# Dolce Home Talleres — Plugin WordPress
**Versión:** 1.0.0  
**Requiere:** WordPress 6.0+, WooCommerce 7.0+, PHP 7.4+

---

## ¿Qué hace este plugin?

Sistema completo de registro y venta de cupos para talleres de mantas de lana XXL de Dolce Home. Integra un panel de administración intuitivo con el proceso de pago de WooCommerce.

---

## Instalación

1. **Subir el plugin**
   - En el panel de WordPress: *Plugins → Agregar nuevo → Subir plugin*
   - Seleccioná el archivo `dolcehome-talleres.zip`
   - Hacé clic en **Instalar ahora**

2. **Activar**
   - Hacé clic en **Activar plugin**
   - El plugin crea automáticamente la tabla `wp_dh_inscripciones` en la base de datos

3. **Verificar WooCommerce**
   - Asegurate de que WooCommerce esté instalado y activo **antes** de activar este plugin

---

## Configuración inicial

### 1. Configurar WooCommerce para pedidos en espera

Por defecto WooCommerce coloca los pedidos en **"En espera"** cuando el cliente elige métodos de pago manuales (transferencia bancaria, etc). Para pagos con tarjeta (Stripe, MercadoPago) los pedidos entran primero en "Pendiente" y luego pasan a "En espera" automáticamente.

Recomendamos configurar **Transferencia bancaria** como método de pago para que el flujo sea:
1. Cliente se registra → pedido en **"En espera"**
2. Cliente transfiere → vos confirmás el pago manualmente
3. Pedido pasa a **"Completado"** → inscripción confirmada

### 2. Página de listado de talleres

Creá una página nueva en WordPress y usá el shortcode:

```
[dh_talleres]
```

Esto mostrará todos los talleres próximos con sus cupos disponibles y el formulario de inscripción.

**Opciones del shortcode:**
```
[dh_talleres mostrar="proximos"]   ← Solo talleres desde hoy en adelante (por defecto)
[dh_talleres mostrar="todos"]      ← Todos los talleres publicados
```

---

## Crear un taller

1. Ir a **Talleres → + Agregar taller** en el menú lateral
2. Escribir el nombre del taller como título (ej: *Taller de Manta XXL — Abril*)
3. Completar los campos en el bloque **"Configuración del Taller"**:

| Campo | Descripción |
|-------|-------------|
| **Fecha** | Fecha del taller (selector de fecha) |
| **Ubicación** | Dirección o lugar del taller |
| **Precio Seña** | Monto de la seña para reservar el cupo (ej: 1600) |
| **Precio Total** | Precio completo del taller (ej: 3500) |
| **Matutino — cupos disponibles** | Cuántos lugares hay ahora mismo (puede ser igual al total al inicio) |
| **Matutino — capacidad máxima** | Total de lugares posibles para el turno |
| **Vespertino — cupos disponibles** | Ídem para el turno vespertino |
| **Vespertino — capacidad máxima** | Ídem para el turno vespertino |

4. Hacer clic en **Publicar**
5. El plugin crea automáticamente un producto en WooCommerce (oculto en la tienda)

---

## Panel de Alumnos Inscriptos

En **Talleres → Alumnos inscriptos** encontrarás:

- **Filtros** por taller y por turno
- **Estadísticas** rápidas: total, en espera, confirmados, cancelados, seña, total
- **Tabla completa** con todos los datos del alumno
- **Exportar a CSV** para Excel (con BOM UTF-8 para caracteres correctos)

### Estados de inscripción

| Estado | Descripción |
|--------|-------------|
| 🕐 En espera | Pedido creado, aguardando pago |
| ✅ Confirmado | Pago recibido (pedido en Completado o Procesando) |
| ❌ Cancelado | Pedido cancelado — cupo repuesto automáticamente |

---

## Lógica de cupos

- Al registrarse, el cupo **se descuenta inmediatamente** para evitar sobreventas
- Si el pedido se cancela (manual o automáticamente), el cupo **se repone** solo
- Si un turno llega a 0 cupos, aparece como **"Sin cupos"** y se deshabilita la selección
- Si ambos turnos están completos, el botón muestra **"Cupos completos"** y no permite registro

---

## Flujo completo del cliente

1. El cliente entra a la página con `[dh_talleres]`
2. Ve los talleres disponibles con fecha, ubicación, cupos y precios
3. Hace clic en **"Inscribirme al taller"**
4. Se abre un modal donde completa:
   - Nombre, email, teléfono
   - Turno (Matutino / Vespertino)
   - Tipo de pago (Seña / Total)
5. Confirma → el sistema crea el pedido WooCommerce en estado **"En espera"**
6. Es redirigido a la página de pago de WooCommerce
7. Completa el pago según el método configurado

---

## Pedidos en WooCommerce

Los pedidos de talleres aparecen en **WooCommerce → Pedidos** con una columna extra **"Taller"** que muestra el taller y turno de cada pedido.

---

## Personalización de colores

Para cambiar los colores del frontend, editá las variables CSS en `public/css/dh-public.css`:

```css
:root {
  --dh-primary:   #8B5E3C;  /* Color marrón principal */
  --dh-accent:    #D4A96A;  /* Dorado del gradiente */
  --dh-green:     #3a9966;  /* Color para precio total */
}
```

---

## Preguntas frecuentes

**¿El plugin crea productos WooCommerce automáticamente?**  
Sí. Al publicar un taller, se crea un producto oculto en WooCommerce que se usa para procesar los pagos. No aparece en la tienda.

**¿Qué pasa si dos personas intentan registrarse al mismo tiempo en el último cupo?**  
El sistema verifica los cupos al momento de crear el pedido. Si los cupos se agotan entre que el cliente abre el formulario y confirma, recibirá un mensaje de error y no se creará el pedido.

**¿Puedo usar el plugin con Stripe o MercadoPago?**  
Sí, funciona con cualquier gateway de pago de WooCommerce. La lógica de cupos funciona independientemente del método de pago.

**¿Cómo cancelo un pedido manualmente?**  
En WooCommerce → Pedidos, abrís el pedido y cambiás el estado a "Cancelado". El cupo se repone automáticamente.

---

## Soporte

Para soporte técnico contactar al desarrollador o abrir un issue en el repositorio del proyecto.
