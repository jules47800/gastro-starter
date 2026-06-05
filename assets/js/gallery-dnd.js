document.addEventListener('DOMContentLoaded', () => {
    // Exécuter seulement sur les écrans larges (desktop)
    if (!window.matchMedia('(min-width: 1025px)').matches) {
        return;
    }

    const galleryGrid = document.querySelector('.gallery-grid');
    if (!galleryGrid) {
        return;
    }
    
    let draggedItem = null;

    // Rendre les éléments déplaçables
    galleryGrid.querySelectorAll('.gallery-item').forEach(item => {
        item.draggable = true;
        item.style.cursor = 'grab';

        item.addEventListener('dragstart', (e) => {
            draggedItem = item;
            // Nécessaire pour Firefox
            e.dataTransfer.setData('text/plain', '');
            
            // Donner un retour visuel
            setTimeout(() => {
                item.classList.add('dragging');
            }, 0);
        });

        item.addEventListener('dragend', () => {
            if (draggedItem) {
                draggedItem.classList.remove('dragging');
            }
        });
    });

    galleryGrid.addEventListener('dragover', (e) => {
        e.preventDefault();
        const afterElement = getDragAfterElement(galleryGrid, e.clientX, e.clientY);
        if (draggedItem) {
            if (afterElement == null) {
                galleryGrid.appendChild(draggedItem);
            } else {
                galleryGrid.insertBefore(draggedItem, afterElement);
            }
        }
    });

    function getDragAfterElement(container, x, y) {
        const draggableElements = [...container.querySelectorAll('.gallery-item:not(.dragging)')];

        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            // On calcule la distance du curseur au centre de l'élément
            const offsetX = x - (box.left + box.width / 2);
            const offsetY = y - (box.top + box.height / 2);
            const distance = Math.sqrt(offsetX * offsetX + offsetY * offsetY);

            if (distance < closest.distance) {
                return { distance: distance, element: child };
            } else {
                return closest;
            }
        }, { distance: Number.POSITIVE_INFINITY }).element;
    }
}); 