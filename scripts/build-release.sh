#!/bin/bash

# Script para crear release del sistema para testeo
# NO sube a GitHub - solo genera archivo local

set -e

RELEASE_VERSION="v2.0.0-test"
RELEASE_NAME="shop-v2-${RELEASE_VERSION}"
BUILD_DIR="build"
RELEASE_DIR="${BUILD_DIR}/${RELEASE_NAME}"

echo "🚀 Creando release ${RELEASE_VERSION} para testeo local..."

# Limpiar directorio de build si existe
if [ -d "$BUILD_DIR" ]; then
    echo "📁 Limpiando directorio de build anterior..."
    rm -rf "$BUILD_DIR"
fi

# Crear estructura de directorios
echo "📁 Creando estructura de directorios..."
mkdir -p "$RELEASE_DIR"

# Copiar instalador
echo "📄 Copiando instalador.php..."
cp instalador.php "$RELEASE_DIR/"

# Copiar README si existe
if [ -f "README_INSTALACION.md" ]; then
    cp README_INSTALACION.md "$RELEASE_DIR/"
fi

# Copiar carpeta app/ (código privado)
echo "📦 Copiando carpeta app/ (código privado)..."
if [ -d "app" ]; then
    cp -r app "$RELEASE_DIR/"
else
    echo "❌ ERROR: No se encontró la carpeta app/"
    exit 1
fi

# Copiar archivos públicos (NO en carpeta public_html, directamente en raíz)
echo "📦 Copiando archivos públicos..."

# Lista de archivos/carpetas públicos a copiar
PUBLIC_ITEMS=(
    "public_html/admin"
    "public_html/api"
    "public_html/assets"
    "public_html/data"
    "public_html/scripts"
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

# Crear archivo .htaccess placeholder en data/ si no existe
if [ ! -f "$RELEASE_DIR/data/.htaccess" ]; then
    echo "📄 Creando .htaccess de seguridad en data/..."
    cat > "$RELEASE_DIR/data/.htaccess" <<'EOF'
# Denegar acceso a todo
Order deny,allow
Deny from all
EOF
fi

# Limpiar archivos innecesarios del release
echo "🧹 Limpiando archivos innecesarios..."

# Eliminar archivos de desarrollo, git, etc.
find "$RELEASE_DIR" -name ".git" -exec rm -rf {} + 2>/dev/null || true
find "$RELEASE_DIR" -name ".gitignore" -delete 2>/dev/null || true
find "$RELEASE_DIR" -name ".DS_Store" -delete 2>/dev/null || true
find "$RELEASE_DIR" -name "node_modules" -exec rm -rf {} + 2>/dev/null || true
find "$RELEASE_DIR" -name ".env" -delete 2>/dev/null || true
find "$RELEASE_DIR" -name "*.log" -delete 2>/dev/null || true

# Crear archivo tar.gz
echo "📦 Creando archivo tar.gz..."
cd "$BUILD_DIR"
tar -czf "${RELEASE_NAME}.tar.gz" "$RELEASE_NAME"
cd ..

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

shop-v2-${RELEASE_VERSION}/
  ├── instalador.php          # Instalador v2 con detección de instalación previa
  ├── app/                    # Código privado (se instalará fuera de public_html)
  │   ├── config/
  │   ├── includes/
  │   ├── pages/
  │   ├── bootstrap.php
  │   └── .htaccess
  ├── admin/                  # Panel de administración (público)
  ├── api/                    # API endpoints (público)
  ├── assets/                 # Recursos estáticos (público)
  ├── data/                   # Directorio de datos (público pero protegido)
  ├── scripts/                # Scripts auxiliares (público)
  ├── index.php               # Página principal (público)
  ├── webhook.php             # Webhook de MercadoPago (público)
  └── .htaccess               # Configuración Apache (público)

========================================
Instrucciones de Instalación
========================================

1. Extraer el archivo tar.gz en el servidor:
   tar -xzf ${RELEASE_NAME}.tar.gz

2. Subir TODO el contenido de la carpeta ${RELEASE_NAME}/ a la ubicación deseada vía FTP

3. Acceder al instalador desde el navegador:
   http://tu-dominio.com/ruta/instalador.php

4. El instalador:
   - Detectará si hay instalación previa
   - Ofrecerá reinstalar (actualizar) o instalar nueva versión
   - Copiará el contenido de app/ a la ruta privada
   - Copiará los archivos públicos a la ruta pública
   - NO creará carpetas intermedias app/ o public_html/

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
