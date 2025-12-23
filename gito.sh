#!/bin/bash

# git-checkout-interactive.sh
# Script interactivo para hacer checkout con flechas

# Colores
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Verificar que estamos en un repo git
if ! git rev-parse --git-dir > /dev/null 2>&1; then
    echo -e "${RED}Error: No estás en un repositorio Git.${NC}"
    exit 1
fi

# Obtener ramas
branches=($(git branch --format='%(refname:short)'))
if [ ${#branches[@]} -eq 0 ]; then
    echo -e "${RED}No se encontraron ramas.${NC}"
    exit 1
fi

# Índice actual
current=0
total=${#branches[@]}

# Función para dibujar el menú
draw_menu() {
    clear
    echo -e "${CYAN}Selecciona una rama para hacer checkout (usa ↑/↓, Enter para confirmar):${NC}\n"
    for i in "${!branches[@]}"; do
        if [ $i -eq $current ]; then
            # Rama actual (resaltada)
            if [[ "${branches[$i]}" == "$(git rev-parse --abbrev-ref HEAD)" ]]; then
                echo -e "${GREEN}➤ ${YELLOW}${branches[$i]}${NC} ${GREEN}(actual)${NC}"
            else
                echo -e "${GREEN}➤ ${YELLOW}${branches[$i]}${NC}"
            fi
        else
            # Otras ramas
            if [[ "${branches[$i]}" == "$(git rev-parse --abbrev-ref HEAD)" ]]; then
                echo "  ${branches[$i]} (actual)"
            else
                echo "  ${branches[$i]}"
            fi
        fi
    done
    echo -e "\n${CYAN}Presiona 'q' para salir.${NC}"
}

# Capturar teclas (requiere modo raw)
trap 'tput cnorm; stty echo; exit 1' INT TERM
tput civis  # Ocultar cursor
stty -echo  # Desactivar eco

draw_menu

while true; do
    read -rsn1 key
    case "$key" in
        A) # Flecha arriba
            ((current--))
            if [ $current -lt 0 ]; then
                current=$((total - 1))
            fi
            draw_menu
            ;;
        B) # Flecha abajo
            ((current++))
            if [ $current -ge $total ]; then
                current=0
            fi
            draw_menu
            ;;
        "") # Enter
            selected_branch="${branches[$current]}"
            echo -e "\n${GREEN}Haciendo checkout a: $selected_branch${NC}"
            if git checkout "$selected_branch" > /dev/null 2>&1; then
                echo -e "${GREEN}✓ Checkout exitoso.${NC}"
            else
                echo -e "${RED}✗ Error al hacer checkout.${NC}"
            fi
            break
            ;;
        q|Q)
            echo -e "\n${CYAN}Saliendo sin cambios.${NC}"
            break
            ;;
    esac
done

# Restaurar terminal
stty echo
tput cnorm
echo
