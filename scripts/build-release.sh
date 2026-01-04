#!/bin/bash
#
# Script de Empaquetado de Release - Shop V2
# Genera el paquete instalable para distribución
#

set -e  # Exit on error

# Colores para output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${BLUE}╔════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║  Shop V2 - Build Release Script           ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════╝${NC}"
echo ""

# Obtener directorio raíz del proyecto
PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_ROOT"

# Leer versión del archivo VERSION
if [ ! -f "VERSION" ]; then
    echo -e "${YELLOW}⚠️  Archivo VERSION no encontrado. Usando 2.6.0 por defecto${NC}"
    VERSION="2.6.0"
else
    VERSION=$(cat VERSION | tr -d '[:space:]')
fi

echo -e "${GREEN}📦 Versión detectada: v${VERSION}${NC}"
echo ""

# Directorios
BUILD_DIR="$PROJECT_ROOT/build"
TEMP_DIR="$BUILD_DIR/temp"
RELEASE_DIR="$TEMP_DIR/shop-v2-v${VERSION}"
OUTPUT_DIR="$BUILD_DIR"
PACKAGE_NAME="shop-v2-v${VERSION}.tar.gz"

# Limpiar builds anteriores
echo -e "${YELLOW}🧹 Limpiando builds anteriores...${NC}"
rm -rf "$TEMP_DIR"
mkdir -p "$TEMP_DIR"
mkdir -p "$OUTPUT_DIR"

# Crear estructura del release
echo -e "${BLUE}📁 Creando estructura del release...${NC}"
mkdir -p "$RELEASE_DIR"

# Copiar carpeta app/ (código privado)
echo -e "${GREEN}  ✓ Copiando app/ (código privado)${NC}"
cp -r "$PROJECT_ROOT/app" "$RELEASE_DIR/"

# Copiar archivos públicos (todo lo que va en public_html/)
echo -e "${GREEN}  ✓ Copiando archivos públicos${NC}"
cp -r "$PROJECT_ROOT/public_html" "$RELEASE_DIR/"

# Copiar archivos de raíz necesarios
echo -e "${GREEN}  ✓ Copiando archivos de configuración${NC}"
cp "$PROJECT_ROOT/.htaccess" "$RELEASE_DIR/" 2>/dev/null || true
cp "$PROJECT_ROOT/VERSION" "$RELEASE_DIR/"
cp "$PROJECT_ROOT/README.md" "$RELEASE_DIR/"
cp "$PROJECT_ROOT/LICENSE.txt" "$RELEASE_DIR/" 2>/dev/null || true

# Copiar instalador a la raíz del paquete (un nivel arriba del release)
echo -e "${GREEN}  ✓ Copiando instalador.php${NC}"
cp "$PROJECT_ROOT/instalador.php" "$TEMP_DIR/"

# Crear README para el paquete
echo -e "${GREEN}  ✓ Creando README de instalación${NC}"
cat > "$TEMP_DIR/LEEME.txt" << 'EOF'
╔════════════════════════════════════════════════════════════╗
║              SHOP V2 - INSTALACIÓN                         ║
╚════════════════════════════════════════════════════════════╝

🚀 INSTALACIÓN RÁPIDA:

1. Sube TODOS los archivos a tu servidor vía FTP:
   - instalador.php (en la raíz)
   - shop-v2-vX.X.X/ (carpeta completa)

2. Accede desde tu navegador a:
   http://tudominio.com/ruta/instalador.php

3. Sigue los 3 pasos del instalador:
   - Paso 1: Bienvenida
   - Paso 2: Configurar rutas (detectadas automáticamente)
   - Paso 3: Crear usuario administrador

4. ¡Listo! El sistema estará instalado en menos de 2 minutos.

📋 ESTRUCTURA ESPERADA:

Tu servidor debería quedar así:

/tudominio.com/
├── instalador.php              ← En la raíz donde accedes
├── shop-v2-vX.X.X/             ← Carpeta del release
│   ├── app/                    ← Se moverá fuera de public_html
│   ├── public_html/            ← Contenido público
│   ├── README.md
│   └── VERSION

Después de la instalación:

/tudominio.com/
├── shop-v2-app/                ← Código privado (fuera de web)
└── public_html/                ← Código público
    ├── index.php
    ├── admin/
    ├── assets/
    └── ...

⚠️  IMPORTANTE:

- El instalador detecta automáticamente las rutas
- Asegúrate de tener permisos de escritura
- El código privado (app/) se moverá FUERA de public_html
- El instalador se auto-elimina después de completar

📚 MÁS INFORMACIÓN:

- Repositorio: https://github.com/pablopeu/shop-v2
- Documentación: Ver README.md en la carpeta del release
- Soporte: GitHub Issues

════════════════════════════════════════════════════════════
EOF

# Limpiar archivos innecesarios del release
echo -e "${YELLOW}🧹 Limpiando archivos innecesarios...${NC}"

# Eliminar .git y archivos de desarrollo
rm -rf "$RELEASE_DIR/.git"
rm -rf "$RELEASE_DIR/.github"
rm -f "$RELEASE_DIR/.gitignore"
rm -rf "$RELEASE_DIR/build"
rm -rf "$RELEASE_DIR/scripts"
rm -rf "$RELEASE_DIR/docs"
rm -rf "$RELEASE_DIR/screenshots"
rm -rf "$RELEASE_DIR/.claude"
rm -f "$RELEASE_DIR/gito.sh"

# Eliminar archivos CLAUDE.* (no necesarios en producción)
rm -f "$RELEASE_DIR/CLAUDE.md"
rm -f "$RELEASE_DIR/CLAUDE.REFERENCE.md"
rm -f "$RELEASE_DIR/CLAUDE.SHIPPING.md"
rm -f "$RELEASE_DIR/CLAUDE.TEMPLATES.md"

# Eliminar archivos de datos (el instalador los crea)
rm -rf "$RELEASE_DIR/app/data/"*.json 2>/dev/null || true
rm -rf "$RELEASE_DIR/app/config/"*.json 2>/dev/null || true
rm -f "$RELEASE_DIR/app/config/config.php" 2>/dev/null || true

# Eliminar archivos debug
rm -f "$RELEASE_DIR/public_html/debug-theme.php" 2>/dev/null || true

# Crear el tarball
echo -e "${BLUE}📦 Empaquetando release...${NC}"
cd "$TEMP_DIR"
tar -czf "$OUTPUT_DIR/$PACKAGE_NAME" .

# Calcular tamaño del paquete
PACKAGE_SIZE=$(du -h "$OUTPUT_DIR/$PACKAGE_NAME" | cut -f1)

echo ""
echo -e "${GREEN}╔════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║           ✅ BUILD COMPLETADO              ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${BLUE}📦 Paquete creado:${NC}"
echo -e "   $OUTPUT_DIR/$PACKAGE_NAME"
echo ""
echo -e "${BLUE}📊 Tamaño:${NC} $PACKAGE_SIZE"
echo ""
echo -e "${YELLOW}📋 Próximos pasos:${NC}"
echo -e "   1. Subir el paquete al release de GitHub:"
echo -e "      ${GREEN}gh release upload v${VERSION} $OUTPUT_DIR/$PACKAGE_NAME${NC}"
echo ""
echo -e "   2. O hacerlo manualmente desde:"
echo -e "      https://github.com/pablopeu/shop-v2/releases/tag/v${VERSION}"
echo ""

# Limpiar directorio temporal (opcional, comentar si quieres inspeccionar)
# echo -e "${YELLOW}🧹 Limpiando archivos temporales...${NC}"
# rm -rf "$TEMP_DIR"

echo -e "${GREEN}✨ ¡Listo!${NC}"
