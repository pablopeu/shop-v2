#!/bin/bash

# Script para crear release del sistema para testeo
# NO sube a GitHub - solo genera archivo local

set -e

RELEASE_VERSION="v2.0.0-test"
RELEASE_NAME="shop-v2-${RELEASE_VERSION}"
BUILD_DIR="build"
RELEASE_DIR="${BUILD_DIR}/${RELEASE_NAME}"
TEMP_DIR="${BUILD_DIR}/temp"

echo "🚀 Creando release ${RELEASE_VERSION} para testeo local..."

# Limpiar directorio de build si existe
if [ -d "$BUILD_DIR" ]; then
    echo "📁 Limpiando directorio de build anterior..."
    rm -rf "$BUILD_DIR"
fi

# Crear estructura de directorios
echo "📁 Creando estructura de directorios..."
mkdir -p "$RELEASE_DIR"
mkdir -p "$TEMP_DIR"

# Copiar carpeta app/ (código privado)
echo "📦 Copiando carpeta app/ (código privado)..."
if [ -d "app" ]; then
    cp -r app "$RELEASE_DIR/"
else
    echo "❌ ERROR: No se encontró la carpeta app/"
    exit 1
fi

# Copiar archivos públicos (NO en carpeta public_html, directamente en raíz del release)
echo "📦 Copiando archivos públicos..."

# Lista de archivos/carpetas públicos a copiar
PUBLIC_ITEMS=(
    "public_html/admin"
    "public_html/api"
    "public_html/assets"
    "public_html/data"
    "public_html/index.php"
    "public_html/webhook.php"
    "public_html/.htaccess"
)

for item in "${PUBLIC_ITEMS[@]}"; do
    if [ -e "$item" ]; then
        # Obtener solo el nombre del archivo/carpeta (sin public_html/)
        basename_item=$(basename "$item")
        echo "  Copiando $basename_item..."
        cp -r "$item" "$RELEASE_DIR/"
    else
        echo "  ⚠️  No encontrado: $item (se omitirá)"
    fi
done

# Copiar README si existe
if [ -f "README_INSTALACION.md" ]; then
    cp README_INSTALACION.md "$RELEASE_DIR/"
fi

# Limpiar archivos de configuración, datos y uploads del release
echo "🧹 Limpiando archivos de configuración, datos y uploads..."

# Eliminar archivos JSON de configuración en app/config/
if [ -d "$RELEASE_DIR/app/config" ]; then
    echo "  Limpiando app/config/*.json..."
    find "$RELEASE_DIR/app/config" -name "*.json" -delete 2>/dev/null || true
    find "$RELEASE_DIR/app/config" -name "*.php" -delete 2>/dev/null || true
fi

# Eliminar archivos JSON de datos en app/data/
if [ -d "$RELEASE_DIR/app/data" ]; then
    echo "  Limpiando app/data/*.json..."
    find "$RELEASE_DIR/app/data" -name "*.json" -delete 2>/dev/null || true
    # Mantener la estructura de directorios pero vacía
    find "$RELEASE_DIR/app/data" -type f -delete 2>/dev/null || true
fi

# Limpiar directorio data/ público (mantener estructura pero vacío)
if [ -d "$RELEASE_DIR/data" ]; then
    echo "  Limpiando data/ público..."
    find "$RELEASE_DIR/data" -type f ! -name ".htaccess" -delete 2>/dev/null || true
fi

# Limpiar uploads (fotos de productos, logos, etc.)
if [ -d "$RELEASE_DIR/assets/uploads" ]; then
    echo "  Limpiando assets/uploads/ (fotos)..."
    find "$RELEASE_DIR/assets/uploads" -type f ! -name ".gitkeep" -delete 2>/dev/null || true
    # Mantener estructura de directorios
    find "$RELEASE_DIR/assets/uploads" -mindepth 1 -type d -empty -delete 2>/dev/null || true
fi

# Crear archivo .htaccess de seguridad en data/ si no existe
if [ ! -f "$RELEASE_DIR/data/.htaccess" ]; then
    echo "📄 Creando .htaccess de seguridad en data/..."
    cat > "$RELEASE_DIR/data/.htaccess" <<'EOF'
# Denegar acceso a todo
Order deny,allow
Deny from all
EOF
fi

# Limpiar archivos innecesarios del release
echo "🧹 Limpiando archivos de desarrollo..."

# Eliminar archivos de desarrollo, git, etc.
find "$RELEASE_DIR" -name ".git" -exec rm -rf {} + 2>/dev/null || true
find "$RELEASE_DIR" -name ".gitignore" -delete 2>/dev/null || true
find "$RELEASE_DIR" -name ".DS_Store" -delete 2>/dev/null || true
find "$RELEASE_DIR" -name "node_modules" -exec rm -rf {} + 2>/dev/null || true
find "$RELEASE_DIR" -name ".env" -delete 2>/dev/null || true
find "$RELEASE_DIR" -name "*.log" -delete 2>/dev/null || true

# Copiar instalador a la raíz del build (fuera de la carpeta del release)
echo "📄 Copiando instalador.php a la raíz..."
cp instalador.php "$TEMP_DIR/"

# Mover carpeta del release al temp
mv "$RELEASE_DIR" "$TEMP_DIR/"

# Crear archivo tar.gz con instalador en la raíz
echo "📦 Creando archivo tar.gz con instalador en raíz..."
cd "$TEMP_DIR"
tar -czf "../${RELEASE_NAME}.tar.gz" instalador.php "$RELEASE_NAME"
cd ../..

# Calcular hash
echo "🔐 Calculando hash SHA256..."
SHA256=$(sha256sum "${BUILD_DIR}/${RELEASE_NAME}.tar.gz" | awk '{print $1}')

# Obtener tamaño
SIZE=$(du -h "${BUILD_DIR}/${RELEASE_NAME}.tar.gz" | awk '{print $1}')

# Crear archivo de información del release
cat > "${BUILD_DIR}/RELEASE_INFO.txt" <<EOF
========================================
SHOP V2 - Release para Testeo
========================================

Versión: ${RELEASE_VERSION}
Fecha: $(date '+%Y-%m-%d %H:%M:%S')
Branch: $(git rev-parse --abbrev-ref HEAD)
Commit: $(git rev-parse --short HEAD)

Archivo: ${RELEASE_NAME}.tar.gz
Tamaño: ${SIZE}
SHA256: ${SHA256}

========================================
Estructura del Paquete
========================================

RAÍZ DEL TAR.GZ:
  instalador.php              # ← Instalador en RAÍZ (acceso inmediato)

shop-v2-${RELEASE_VERSION}/   # ← Carpeta con el sistema
  ├── app/                    # Código privado (se instalará fuera de public_html)
  │   ├── config/             # ← VACÍO (sin archivos JSON de configuración)
  │   ├── data/               # ← VACÍO (sin productos, ventas, usuarios)
  │   ├── includes/
  │   ├── pages/
  │   ├── bootstrap.php
  │   └── .htaccess
  ├── admin/                  # Panel de administración (público)
  ├── api/                    # API endpoints (público)
  ├── assets/                 # Recursos estáticos (público)
  │   └── uploads/            # ← VACÍO (sin fotos de productos/logos)
  ├── data/                   # ← VACÍO (sin datos, solo .htaccess)
  ├── index.php               # Página principal (público)
  ├── webhook.php             # Webhook de MercadoPago (público)
  └── .htaccess               # Configuración Apache (público)

NOTA: Sistema limpio sin configuración ni datos del desarrollo local

========================================
Instrucciones de Instalación
========================================

OPCIÓN A: Instalación vía FTP (Recomendada)
--------------------------------------------
1. Extraer el tar.gz localmente:
   tar -xzf ${RELEASE_NAME}.tar.gz

2. Subir VÍA FTP a la carpeta donde quieres instalar:
   - instalador.php (quedará en la raíz de tu carpeta)
   - ${RELEASE_NAME}/ (carpeta completa)

3. Acceder al instalador desde el navegador:
   http://tu-dominio.com/ruta/instalador.php

   ✅ El instalador está en la raíz, NO necesitas entrar a ninguna subcarpeta

4. El instalador automáticamente:
   - Detectará la carpeta ${RELEASE_NAME}/
   - Leerá app/ y archivos públicos de allí
   - Los copiará a las rutas que configures
   - NO creará carpetas intermedias

OPCIÓN B: Instalación vía SSH
--------------------------------------------
1. Subir el tar.gz al servidor vía FTP/SCP

2. Conectar por SSH y extraer:
   tar -xzf ${RELEASE_NAME}.tar.gz

3. Acceder al instalador:
   http://tu-dominio.com/ruta/instalador.php

IMPORTANTE:
- El instalador lee desde ${RELEASE_NAME}/
- Después de instalar, puedes eliminar la carpeta ${RELEASE_NAME}/
- El sistema quedará instalado en las rutas que configures

========================================
Características del Instalador v2
========================================

✅ Detección automática de instalación previa
✅ Modo reinstalación: actualiza código sin tocar JSON
✅ Modo nueva versión: instalación paralela con validación
✅ Protección de archivos de configuración en reinstalación
✅ Creación de JSON dummy solo si no existen
✅ Validación de conflictos antes de sobrescribir

========================================
EOF

# Mostrar resumen
echo ""
echo "✅ Release creado exitosamente!"
echo ""
echo "📦 Archivo: ${BUILD_DIR}/${RELEASE_NAME}.tar.gz"
echo "📊 Tamaño: ${SIZE}"
echo "🔐 SHA256: ${SHA256}"
echo ""
echo "📋 Información completa: ${BUILD_DIR}/RELEASE_INFO.txt"
echo ""
echo "🧪 Para testear:"
echo "   1. Extraer: tar -xzf ${BUILD_DIR}/${RELEASE_NAME}.tar.gz"
echo "   2. Inspeccionar: ls -la ${BUILD_DIR}/${RELEASE_NAME}/"
echo "   3. Subir vía FTP a servidor de pruebas"
echo ""
