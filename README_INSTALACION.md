# Shop V2 - Instalación por FTP

## 📦 Instalación Rápida

### Paso 1: Subir Archivos por FTP

1. Descarga el archivo `shop-v2-v1.0.0.zip` desde la página de releases
2. Extrae el contenido del ZIP en tu computadora
3. Conecta por FTP a tu hosting
4. Sube **todos los archivos** a una carpeta en tu hosting:
   - Opción A: Directamente en `public_html/` o `www/` (raíz del sitio)
   - Opción B: En un subdirectorio `public_html/shop/`

### Paso 2: Acceder al Instalador

1. Abre tu navegador y accede a:
   - Si subiste a la raíz: `http://tu-dominio.com/install/`
   - Si subiste a subdirectorio: `http://tu-dominio.com/shop/install/`

2. El instalador te guiará en 3 simples pasos:
   - ✅ **Paso 1**: Confirmar rutas detectadas automáticamente
   - ✅ **Paso 2**: Crear usuario administrador
   - ✅ **Paso 3**: Instalación automática

3. **Tiempo estimado**: Menos de 2 minutos

### Paso 3: Eliminar Instalador

⚠️ **MUY IMPORTANTE**: Después de completar la instalación, el instalador se auto-eliminará por seguridad.

---

## 🔒 Seguridad (Recomendado)

### Opción A: Mover carpeta `app/` fuera de public_html

**Para MÁXIMA seguridad**, mueve la carpeta `app/` fuera del directorio público:

```
ANTES:
/public_html/
  ├── app/         ⚠️ Accesible desde web
  ├── admin/
  ├── assets/
  └── index.php

DESPUÉS:
/
├── /shop-v2-app/  ✅ NO accesible desde web
└── /public_html/
    ├── admin/
    ├── assets/
    └── index.php
```

**Cómo hacerlo**:

1. Por FTP, crea una carpeta `shop-v2-app` **fuera** de `public_html/`
2. Mueve la carpeta `app/` a `shop-v2-app/`
3. Actualiza `app/config/config.php`:
   ```php
   'app_path' => '/home/tu-usuario/shop-v2-app',
   'public_path' => '/home/tu-usuario/public_html',
   ```

### Opción B: Dejar `app/` dentro (Protegida con .htaccess)

Si no puedes mover `app/` fuera, **NO te preocupes**:

- ✅ La carpeta `app/` está protegida con `.htaccess` que **bloquea TODO acceso**
- ✅ El archivo `config.php` nunca se commitea a Git
- ✅ Los archivos JSON de datos están protegidos
- ✅ El sistema es seguro de todas formas

---

## 📋 Requisitos del Hosting

### Mínimos

- ✅ PHP 7.4 o superior (recomendado PHP 8.0+)
- ✅ Extensión PHP: `json` (incluida por defecto)
- ✅ Extensión PHP: `mbstring`
- ✅ Permisos de escritura en las carpetas del proyecto
- ✅ Soporte para `.htaccess` (Apache) o configuración equivalente (Nginx)

### Recomendados

- ✅ PHP 8.1 o superior
- ✅ HTTPS configurado
- ✅ Acceso SSH (opcional, para mayor control)
- ✅ Certificado SSL gratuito (Let's Encrypt)

---

## 🎯 Después de Instalar

### Accede al Panel de Administración

1. Ve a: `http://tu-dominio.com/admin/login.php`
2. Ingresa las credenciales que creaste en el instalador
3. **Configura tu tienda**:
   - Nombre del sitio
   - Logo
   - Descripción
   - MercadoPago (opcional)

### Agrega Productos

1. Ve a: Admin → Productos → Nuevo Producto
2. Completa los datos del producto
3. Sube imágenes
4. ¡Listo!

---

## 🆘 Problemas Comunes

### Error 500 (Internal Server Error)

**Causa**: Problemas con `.htaccess` o permisos

**Solución**:
1. Verifica que tu hosting soporte `.htaccess`
2. Verifica permisos de archivos (0644 para archivos, 0755 para carpetas)
3. Revisa el log de errores de PHP

### No puedo acceder al admin

**Solución**:
1. Verifica la URL: `http://tu-dominio.com/admin/login.php`
2. Si usas subdirectorio, ajusta el `base_path` en `config.php`
3. Verifica que el archivo `.htaccess` esté correctamente configurado

### Errores de permisos

**Solución**:
1. Las carpetas deben tener permisos `0755`
2. Los archivos deben tener permisos `0644`
3. La carpeta `app/data/` debe ser escribible

---

## 📞 Soporte

- **GitHub Issues**: https://github.com/pablopeu/shop-v2/issues
- **Documentación**: Ver `CLAUDE.md` en el repositorio

---

## 📄 Licencia

Este proyecto es de código abierto. Ver LICENSE para más detalles.

---

**¡Feliz venta!** 🎉
