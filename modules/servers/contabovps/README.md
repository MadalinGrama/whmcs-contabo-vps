# ContaboVPS — WHMCS Provisioning Module

Módulo de provisioning para WHMCS 8.x que integra la [API de Contabo](https://api.contabo.com/) para gestión de VPS desde el panel del cliente.

**Versión:** 1.0.0  
**Autor:** Ed Álvarez — Plus Soluciones  
**Compatible con:** WHMCS 8.1.3+, PHP 8.2  
**Licencia:** MIT  

---

## Características

- Panel de cliente con estado del VPS en tiempo real
- Controles de energía: encender, apagar, reiniciar, shutdown suave
- Gestión de snapshots: crear, listar, eliminar
- Panel de administración con información detallada
- Autenticación OAuth2 con refresco automático de token
- Sin dependencias externas (usa cURL nativo de PHP)
- Código PHP limpio — sin IonCube

---

## Requisitos

- WHMCS 8.1.3 o superior
- PHP 8.2+
- Extensión `curl` habilitada en PHP
- Cuenta en [Contabo](https://contabo.com/) con acceso API
- Credenciales OAuth2 de Contabo (Client ID + Secret)

---

## Instalación

1. **Copiar el módulo** al directorio de WHMCS:

   ```bash
   cp -r modules/servers/contabovps /path/to/whmcs/modules/servers/
   ```

   La estructura final debe ser:
   ```
   whmcs/
   └── modules/
       └── servers/
           └── contabovps/
               ├── contabovps.php
               ├── lib/
               │   └── ContaboApi.php
               └── templates/
                   ├── clientarea.tpl
                   └── admin.tpl
   ```

2. **Permisos** (recomendado):
   ```bash
   chmod 644 modules/servers/contabovps/*.php
   chmod 644 modules/servers/contabovps/lib/*.php
   chmod 644 modules/servers/contabovps/templates/*.tpl
   ```

---

## Configuración en WHMCS Admin

### 1. Crear el Server Group

1. Ve a **Setup → Products/Services → Servers**
2. Clic en **Add Server**
3. Completa los campos:
   - **Name:** `Contabo API Server`
   - **Hostname:** `api.contabo.com` (referencial, no se usa para conexión)
   - **Module:** `contabovps`
4. En la sección **Module Settings**, ingresa las credenciales:

| Campo | Descripción | Ejemplo |
|-------|-------------|---------|
| Client ID (OAuth2) | ID de cliente OAuth2 de Contabo | `INT-12345678` |
| Client Secret (OAuth2) | Secret OAuth2 | `AbCdEfGh...` |
| API User (email) | Email de tu cuenta Contabo | `tu@email.com` |
| API Password | Contraseña de tu cuenta Contabo | `tuPassword` |

5. Clic en **Test Connection** (opcional) y luego **Save Changes**

### 2. Crear el producto VPS

1. Ve a **Setup → Products/Services → Products/Services**
2. Clic en **Create a New Product**
3. Selecciona **Server/VPS** como tipo
4. En la pestaña **Module Settings**:
   - **Module:** `contabovps`
   - **Server Group:** el grupo creado anteriormente
5. En la pestaña **Custom Fields**, crea el campo:
   - **Field Name:** `instance_id`
   - **Field Type:** Text Box
   - **Admin Only:** ✅ (el cliente no debe poder editarlo)
   - **Required Field:** ✅

---

## Asignación del Instance ID al servicio del cliente

Cuando un cliente contrata el servicio VPS:

1. Ve a **Clients → [cliente] → Products/Services → [servicio]**
2. Haz clic en **Edit**
3. En la sección **Custom Fields**, ingresa el **Instance ID** de Contabo
   - Puedes encontrar el ID en el panel de Contabo o via API: `GET /v1/compute/instances`
4. Guarda los cambios

> **Nota:** El módulo no crea instancias automáticamente en Contabo. Debes crear la instancia manualmente en el panel/API de Contabo y luego asignar su ID al servicio en WHMCS.

---

## Config Fields (referencia)

| configoption | Campo | Descripción |
|:---:|-------|-------------|
| `configoption1` | Client ID | OAuth2 Client ID (ej: `INT-12345678`) |
| `configoption2` | Client Secret | OAuth2 Client Secret |
| `configoption3` | API User | Email de la cuenta Contabo |
| `configoption4` | API Password | Contraseña de la cuenta Contabo |

---

## Comportamiento de las acciones de provisioning

| Acción WHMCS | Comportamiento |
|---|---|
| **Create Account** | Verifica que el `instance_id` exista en Contabo. No crea VPS. |
| **Suspend Account** | Apaga (power-off forzado) la instancia. |
| **Unsuspend Account** | Enciende (power-on) la instancia. |
| **Terminate Account** | Registra log y devuelve `success`. **NO elimina** la instancia en Contabo. |

> ⚠️ **Terminate** no borra la instancia en Contabo por seguridad. El administrador debe descomisionarla manualmente.

---

## Panel del cliente — Acciones disponibles

- **Encender** — Power on (disponible solo si está apagada)
- **Apagar** — Force power off (disponible solo si está encendida)
- **Reiniciar** — Hard reboot (disponible solo si está encendida)
- **Shutdown** — ACPI graceful shutdown (disponible solo si está encendida)
- **Crear Snapshot** — Crea un snapshot con nombre personalizado
- **Eliminar Snapshot** — Elimina un snapshot existente (requiere confirmación JS)

---

## Limitaciones conocidas de la API de Contabo

1. **Rate limiting:** La API de Contabo aplica rate limiting. Evita llamadas en bucle rápido. El módulo hace una llamada por carga de página.

2. **Token expiry:** El access token OAuth2 expira en ~300 segundos. El módulo lo refresca automáticamente, pero en entornos con muchas sesiones concurrentes, considera implementar caché compartido (ej. Redis/Memcached).

3. **Creación de instancias:** La API de Contabo **no permite crear VPS programáticamente** desde módulos de terceros de forma directa — el proceso de order/checkout de Contabo es manual. Por eso `CreateAccount` solo verifica la existencia del VPS.

4. **Snapshots:** Contabo tiene un límite de snapshots por instancia (varía según el plan). Consulta tu contrato.

5. **x-request-id:** Cada llamada a la API requiere el header `x-request-id` con un UUID único. El módulo lo genera automáticamente.

6. **Acciones pendientes:** Las acciones de power (start/stop/restart) son asíncronas. El estado puede tardar unos segundos en reflejarse. Recarga la página para ver el estado actualizado.

7. **Reinstall:** La función `reinstallInstance` está implementada en `ContaboApi.php` pero no está expuesta en el panel del cliente por seguridad. Si la necesitas, agrégala como función admin.

---

## Soporte

Para problemas con el módulo, revisa los logs en:
- **WHMCS Admin → Utilities → Logs → Module Log**

Para problemas con la API de Contabo:
- [Documentación oficial](https://api.contabo.com/)
- [Portal de soporte Contabo](https://contabo.com/en/support/)
