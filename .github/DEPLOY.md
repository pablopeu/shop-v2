# 🚀 Guía de Deploy Automático

## GitHub Action - Deploy to FTP

Este repositorio incluye un GitHub Action que despliega automáticamente a tu servidor FTP.

## 📋 Configuración de Secrets

Necesitas configurar 3 secrets en GitHub:

### 1. Ve a GitHub Repository Settings
```
Tu Repositorio → Settings → Secrets and variables → Actions → New repository secret
```

### 2. Crea los siguientes secrets:

#### `FTP_SERVER`
- **Nombre**: FTP_SERVER
- **Valor**: ftp.tu-dominio.com (o IP del servidor FTP)
- **Ejemplo**: ftp.mihosting.com

#### `FTP_USERNAME`
- **Nombre**: FTP_USERNAME
- **Valor**: Tu usuario FTP
- **Ejemplo**: usuario@midominio.com

#### `FTP_PASSWORD`
- **Nombre**: FTP_PASSWORD
- **Valor**: Tu contraseña FTP
- **Nota**: ⚠️ Mantener segura - nunca la commits al código

## 📁 Estructura de Deploy

El Action despliega en esta estructura:

```
/
├── shop-v2-app/              ← Código privado (app/)
│   ├── config/
│   ├── includes/
│   ├── pages/
│   └── data/
│
└── public_html/
    └── shopv2/               ← Código público (public_html/)
        ├── index.php
        ├── admin/
        ├── webhook.php
        ├── assets/
        └── install/          ← Instalador (con auto-eliminación)
            └── installer.php
```

## 🔄 Cómo Funciona

### Trigger Automático
El deploy se ejecuta automáticamente en cada push a `main`:

```bash
git add .
git commit -m "feat: nueva funcionalidad"
git push origin main
# ↓ GitHub Action se ejecuta automáticamente
```

### Trigger Manual
También puedes ejecutarlo manualmente:

1. Ve a: `Actions` tab en GitHub
2. Selecciona: `Deploy to FTP`
3. Click: `Run workflow`
4. Select branch: `main`
5. Click: `Run workflow`

## 📝 Proceso de Deploy

1. **Checkout**: Descarga el código del repositorio
2. **Deploy app/**: Sube código privado a `/shop-v2-app/`
3. **Deploy public_html/**: Sube código público (incluye instalador) a `/public_html/shopv2/`
4. **Summary**: Muestra resumen del deploy

## ⚙️ Configuración del Servidor Web

### Acceso al Instalador
```
http://tu-dominio.com/shopv2/install/installer.php
```

**IMPORTANTE**: El instalador está en una carpeta separada porque necesitas acceso temporal para configurar el sistema.

### Configurar Document Root

#### En cPanel
1. Ve a: `Dominios` o `Addon Domains`
2. Edita el dominio
3. Cambia Document Root a: `/public_html/shopv2`

#### En Plesk
1. Ve a: `Hosting Settings`
2. Cambia Document root a: `/public_html/shopv2`

#### En archivo .htaccess (alternativa)
Si no puedes cambiar el document root, crea `.htaccess` en `/public_html/`:

```apache
# Redirigir todo a shopv2/
RewriteEngine On
RewriteCond %{REQUEST_URI} !^/shopv2/
RewriteRule ^(.*)$ /shopv2/$1 [L]
```

## 🔒 Después de la Instalación

### 1. Ejecutar el Instalador
```
http://tu-dominio.com/shopv2/install/installer.php
```

El instalador detectará automáticamente las rutas correctas. Configurar:
- **App Path**: Auto-detectado (verifica que sea correcto)
- **Public Path**: Auto-detectado (verifica que sea correcto)
- **App URL**: `http://tu-dominio.com`
- **Base Path**: `/shopv2` (o vacío si es dominio dedicado)

### 2. Usar Auto-Eliminación

Al finalizar la instalación, el instalador mostrará un botón rojo:
```
🗑️ Eliminar Instalador y Finalizar
```

**Recomendado:** Usa este botón para eliminar automáticamente la carpeta `/install/`

**Alternativa manual (FTP/SSH):**
```bash
rm -rf /public_html/shopv2/install/
```

### 3. Verificar Permisos

```bash
chmod 750 /shop-v2-app/
chmod 640 /shop-v2-app/config/config.php
chmod 750 /shop-v2-app/data/
```

## 🔍 Verificación de Seguridad

### ✅ Deben funcionar:
```
http://tu-dominio.com/shopv2/
http://tu-dominio.com/shopv2/admin/
http://tu-dominio.com/shopv2/admin/login.php
```

### ❌ Deben fallar (403):
```
http://tu-dominio.com/../../shop-v2-app/config/config.php
http://tu-dominio.com/../../shop-v2-app/includes/functions.php
http://tu-dominio.com/shopv2/install/ (después de eliminar)
```

## 🐛 Troubleshooting

### Error: "app path not found"
- Verificar que `/shop-v2-app/` exista en el servidor
- Verificar que el instalador configuró correctamente las rutas

### Error: "500 Internal Server Error"
- Verificar permisos de archivos
- Revisar logs del servidor
- Verificar que PHP tiene acceso a `/shop-v2-app/`

### Deploy falla en GitHub Actions
- Verificar que los secrets estén configurados correctamente
- Verificar credenciales FTP
- Verificar que las rutas existen en el servidor

## 📊 Monitoreo de Deploys

Ver historial de deploys:
```
GitHub Repository → Actions tab → Deploy to FTP
```

Cada deploy muestra:
- ✅ Paso completado
- ❌ Paso fallido
- 📝 Logs detallados

## 🔄 Rollback

Si algo sale mal:

1. Ve a `Actions` tab
2. Encuentra el último deploy exitoso
3. Click en `Re-run jobs`

O usa FTP para restaurar archivos manualmente.

## 📞 Soporte

Si tienes problemas:
1. Revisa los logs en GitHub Actions
2. Verifica configuración de secrets
3. Verifica permisos del servidor FTP
4. Contacta a tu proveedor de hosting si las rutas no son accesibles

---

**Última actualización**: 2025-11-24
